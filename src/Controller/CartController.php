<?php

declare(strict_types=1);

namespace ModernaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\HttpFoundation\Session\Session as TheliaSession;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Model\ConfigQuery;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\CouponQuery;

#[Route('/cart', name: 'moderna_cart_')]
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartFacade $cartFacade,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'translator')]
        private readonly TranslatorInterface $translator
    ) {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['pseId'], $data['quantity'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Missing required parameters'
                ], 400);
            }

            $pseId = (int) $data['pseId'];
            $quantity = (int) $data['quantity'];
            $append = $data['append'] ?? true;

            // Get PSE to retrieve product ID
            $pse = \Thelia\Model\ProductSaleElementsQuery::create()->findPk($pseId);
            if (!$pse) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Product not found'
                ], 404);
            }

            // Check stock availability
            $productId = $pse->getProductId();
            $availableStock = $pse->getQuantity();

            // CRITICAL FIX: Get cart by cookie token instead of session to fix shared cart bug
            $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
            $cookieToken = $request->cookies->get($cookieName);

            $cart = null;
            if ($cookieToken) {
                // Try to find cart by cookie token
                $cart = \Thelia\Model\CartQuery::create()->findOneByToken($cookieToken);
            }

            if (!$cart) {
                // No cart found by cookie, create new one
                $cart = new \Thelia\Model\Cart();
                $token = bin2hex(random_bytes(16));
                $cart->setToken($token);
                $cart->save();

                // Update session
                $request->getSession()->setSessionCart($cart);
            }

            // Reload cart to get fresh cart items
            $cart->reload();

            // Check if this PSE already exists in cart
            $existingItem = null;
            foreach ($cart->getCartItems() as $cartItem) {
                if ($cartItem->getProductSaleElementsId() === $pseId) {
                    $existingItem = $cartItem;
                    break;
                }
            }

            // Calculate total quantity needed
            $currentQuantityInCart = $existingItem ? $existingItem->getQuantity() : 0;
            $totalQuantityNeeded = $append ? ($currentQuantityInCart + $quantity) : $quantity;

            // Check if enough stock is available
            if ($totalQuantityNeeded > $availableStock) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $this->translator->trans(
                        'Insufficient stock. Only %qty available.',
                        ['%qty' => $availableStock]
                    )
                ], 400);
            }

            if ($existingItem && $append) {
                // Update existing item quantity
                $newQuantity = (int)$totalQuantityNeeded;
                $this->cartFacade->updateItemQuantity(
                    new CartItemUpdateQuantityDTO(
                        cart: $cart,
                        cartItemId: $existingItem->getId(),
                        quantity: $newQuantity
                    )
                );
            } else {
                // Add new item using CartFacade
                $this->cartFacade->addItem(
                    new CartItemAddDTO(
                        cart: $cart,
                        productId: $productId,
                        productSaleElementId: $pseId,
                        quantity: $quantity,
                        append: $append,
                        newness: false
                    )
                );
            }

            // Get updated cart count
            $cartItems = $cart->getCartItems();
            $totalQuantity = 0;
            foreach ($cartItems as $item) {
                $totalQuantity += $item->getQuantity();
            }

            $response = new JsonResponse([
                'success' => true,
                'cartCount' => $totalQuantity
            ]);

            // CRITICAL: Add cart cookie to response to fix session isolation
            if ($cart->getToken()) {
                $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
                $cookieLifetime = ConfigQuery::read('cart.cookie_lifetime', 60 * 60 * 24 * 365);

                $response->headers->setCookie(
                    new Cookie(
                        $cookieName,
                        $cart->getToken(),
                        time() + $cookieLifetime,
                        '/',
                        null,
                        false,
                        true
                    )
                );

            }

            return $response;

        } catch (\TypeError $e) {
            // Handle type errors (like int vs float issues)
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('An error occurred while adding to cart. Please try again.')
            ], 500);
        } catch (\Exception $e) {
            // Check if it's a stock-related error
            $errorMessage = $e->getMessage();
            if (stripos($errorMessage, 'stock') !== false ||
                stripos($errorMessage, 'quantity') !== false ||
                stripos($errorMessage, 'available') !== false) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $this->translator->trans('Insufficient stock for this product.')
                ], 400);
            }

            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('An error occurred: %msg', ['%msg' => $errorMessage])
            ], 500);
        }
    }

    #[Route('/update-quantity', name: 'update_quantity', methods: ['POST'])]
    public function updateQuantity(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['cartItemId'], $data['quantity'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Missing required parameters'
                ], 400);
            }

            $cartItemId = (int) $data['cartItemId'];
            $quantity = (int) $data['quantity'];

            if ($quantity < 1) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Quantity must be at least 1'
                ], 400);
            }

            // CRITICAL: Get cart by cookie token to ensure isolation
            $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
            $cookieToken = $request->cookies->get($cookieName);

            if (!$cookieToken) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No cart found'
                ], 404);
            }

            $cart = \Thelia\Model\CartQuery::create()->findOneByToken($cookieToken);
            if (!$cart) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Cart not found'
                ], 404);
            }

            // Find the cart item
            $cartItem = \Thelia\Model\CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->findPk($cartItemId);

            if (!$cartItem) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Cart item not found'
                ], 404);
            }

            // Check stock availability
            $pse = $cartItem->getProductSaleElements();
            $availableStock = $pse->getQuantity();

            if ($quantity > $availableStock) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $this->translator->trans(
                        'Insufficient stock. Only %qty available.',
                        ['%qty' => $availableStock]
                    )
                ], 400);
            }

            // Update quantity using CartFacade
            $this->cartFacade->updateItemQuantity(
                new CartItemUpdateQuantityDTO(
                    cart: $cart,
                    cartItemId: $cartItemId,
                    quantity: $quantity
                )
            );

            // Reload cart item to get updated prices
            $cartItem->reload();

            // Calculate total quantity and cart total
            $cart->reload();
            $totalQuantity = 0;
            $cartTotal = 0;
            foreach ($cart->getCartItems() as $item) {
                $totalQuantity += $item->getQuantity();
                $itemPrice = $item->getPromo() ? $item->getPromoPrice() : $item->getPrice();
                $cartTotal += $itemPrice * $item->getQuantity();
            }

            $response = new JsonResponse([
                'success' => true,
                'cartCount' => $totalQuantity,
                'cartTotal' => $cartTotal,
                'itemTotal' => ($cartItem->getPromo() ? $cartItem->getPromoPrice() : $cartItem->getPrice()) * $cartItem->getQuantity()
            ]);

            // Set cookie in response
            if ($cart->getToken()) {
                $cookieLifetime = ConfigQuery::read('cart.cookie_lifetime', 60 * 60 * 24 * 365);
                $response->headers->setCookie(
                    new Cookie(
                        $cookieName,
                        $cart->getToken(),
                        time() + $cookieLifetime,
                        '/',
                        null,
                        false,
                        true
                    )
                );
            }


            return $response;

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('An error occurred: %msg', ['%msg' => $e->getMessage()])
            ], 500);
        }
    }

    #[Route('/coupon', name: 'coupon_apply', methods: ['POST'])]
    public function applyCoupon(Request $request): JsonResponse
    {

        try {
            // Get locale from Thelia session
            $session = $request->getSession();
            $locale = 'en_US';
            if ($session instanceof TheliaSession) {
                $lang = $session->getLang();
                if ($lang) {
                    $locale = $lang->getLocale();
                }
            }

            $data = json_decode($request->getContent(), true);
            $couponCode = $data['coupon'] ?? '';

            if (empty($couponCode)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->translator->trans('Please enter a promo code', [], 'messages', $locale)
                ], 400);
            }

            // Check if coupon exists in database before dispatching event
            // This prevents a TypeError in Thelia's BaseFacade::findOneCouponByCode which doesn't handle null
            $coupon = CouponQuery::create()->findOneByCode($couponCode);
            if ($coupon === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->translator->trans('This promo code does not exist', [], 'messages', $locale)
                ], 400);
            }

            // Create and dispatch Thelia coupon consume event
            $event = new CouponConsumeEvent($couponCode);
            $this->eventDispatcher->dispatch($event, TheliaEvents::COUPON_CONSUME);

            // Check if coupon was valid
            if (!$event->getIsValid()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->translator->trans('This promo code is not valid or does not meet the conditions', [], 'messages')
                ], 400);
            }

            // Recalculate cart with coupon
            $cart = $this->cartFacade->getOrCreateFromSession();
            $this->cartFacade->recalculatePostage($cart);
            $cart->save();

            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('Promo code applied successfully', [], 'messages'),
                'discount' => $event->getDiscount()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/product-attributes/{productId}', name: 'product_attributes', methods: ['GET'])]
    public function getProductAttributes(int $productId, Request $request): JsonResponse
    {
        try {
            $product = ProductQuery::create()->findPk($productId);
            if (!$product) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Product not found'
                ], 404);
            }

            // Get current locale from session for proper translations
            $locale = $request->getSession()->getLang()?->getLocale() ?? 'en_US';

            // Get all PSE with their attribute combinations
            $pses = ProductSaleElementsQuery::create()
                ->filterByProductId($productId)
                ->filterByVisible(true)
                ->orderByPosition()
                ->find();

            $attributesMap = [];
            $pseData = [];

            // Get all product images as fallback
            $productImages = [];
            foreach ($product->getProductImages() as $productImage) {
                if ($productImage->getVisible()) {
                    $productImages[] = $productImage->getId();
                }
            }

            $pseIndex = 0;
            foreach ($pses as $pse) {
                $pseAttributes = [];

                foreach ($pse->getAttributeCombinations() as $combination) {
                    $attribute = $combination->getAttribute();
                    $attributeAv = $combination->getAttributeAv();

                    $attributeId = $attribute->getId();
                    $attributeAvId = $attributeAv->getId();

                    // Set locale for proper translations
                    $attribute->setLocale($locale);
                    $attributeAv->setLocale($locale);

                    // Build attributes map
                    if (!isset($attributesMap[$attributeId])) {
                        $attributesMap[$attributeId] = [
                            'id' => $attributeId,
                            'title' => $attribute->getTitle(),
                            'position' => $attribute->getPosition(),
                            'values' => []
                        ];
                    }

                    // Add attribute value if not already present
                    if (!isset($attributesMap[$attributeId]['values'][$attributeAvId])) {
                        $attributesMap[$attributeId]['values'][$attributeAvId] = [
                            'id' => $attributeAvId,
                            'title' => $attributeAv->getTitle(),
                            'position' => $attributeAv->getPosition()
                        ];
                    }

                    // Store attribute combination for this PSE
                    $pseAttributes[$attributeId] = $attributeAvId;
                }

                // Get images associated with this PSE
                $pseImages = [];
                foreach ($pse->getProductSaleElementsProductImages() as $pseProductImage) {
                    $productImage = $pseProductImage->getProductImage();
                    if ($productImage && $productImage->getVisible()) {
                        $pseImages[] = $productImage->getId();
                    }
                }

                // Fallback: if PSE has no specific images, assign different images based on PSE index
                if (empty($pseImages)) {
                    if (!empty($productImages)) {
                        // Assign one main image per PSE based on its position
                        $imageIndex = $pseIndex % count($productImages);
                        $pseImages = [$productImages[$imageIndex]];

                    }
                }

                $pseIndex++;

                $pseData[] = [
                    'id' => $pse->getId(),
                    'ref' => $pse->getRef(),
                    'quantity' => $pse->getQuantity(),
                    'combination' => $pseAttributes,  // Map of attribute_id => attribute_av_id
                    'images' => $pseImages  // Array of image IDs (or all product images if none specific)
                ];
            }

            // Convert values to arrays and sort
            foreach ($attributesMap as &$attr) {
                $attr['values'] = array_values($attr['values']);
                usort($attr['values'], fn($a, $b) => $a['position'] <=> $b['position']);
            }

            $attributes = array_values($attributesMap);
            usort($attributes, fn($a, $b) => $a['position'] <=> $b['position']);

            return new JsonResponse([
                'success' => true,
                'attributes' => $attributes,
                'pses' => $pseData
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/change-pse', name: 'change_pse', methods: ['POST'])]
    public function changePse(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['cartItemId'], $data['pseId'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Missing required parameters'
                ], 400);
            }

            $cartItemId = (int) $data['cartItemId'];
            $newPseId = (int) $data['pseId'];

            // Get cart by cookie token
            $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
            $cookieToken = $request->cookies->get($cookieName);

            if (!$cookieToken) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No cart found'
                ], 404);
            }

            $cart = \Thelia\Model\CartQuery::create()->findOneByToken($cookieToken);
            if (!$cart) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Cart not found'
                ], 404);
            }

            // Find the cart item
            $cartItem = CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->findPk($cartItemId);

            if (!$cartItem) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Cart item not found'
                ], 404);
            }

            // Get the new PSE
            $newPse = ProductSaleElementsQuery::create()->findPk($newPseId);
            if (!$newPse) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Product variant not found'
                ], 404);
            }

            // Check stock availability
            $currentQuantity = $cartItem->getQuantity();
            if ($currentQuantity > $newPse->getQuantity()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $this->translator->trans(
                        'Insufficient stock for this variant. Only %qty available.',
                        ['%qty' => $newPse->getQuantity()]
                    )
                ], 400);
            }

            // Update the PSE for this cart item
            $cartItem->setProductSaleElementsId($newPseId);

            // Recalculate prices (Thelia will handle this on save)
            $cartItem->save();

            // Reload to get updated prices
            $cartItem->reload();


            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('Product variant updated')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('An error occurred: %msg', ['%msg' => $e->getMessage()])
            ], 500);
        }
    }

    #[Route('/coupon/clear', name: 'coupon_clear', methods: ['POST'])]
    public function clearCoupon(Request $request): JsonResponse
    {

        try {
            // Get coupon code from query parameter
            $couponCode = $request->query->get('code');
            error_log('Coupon code to remove: ' . ($couponCode ?? 'null'));

            if (empty($couponCode)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->translator->trans('No coupon code provided')
                ], 400);
            }

            // Get session and remove coupon from consumed coupons
            $session = $request->getSession();
            $consumedCoupons = $session->getConsumedCoupons();
            error_log('Consumed coupons before removal: ' . json_encode($consumedCoupons));

            if (isset($consumedCoupons[$couponCode])) {
                unset($consumedCoupons[$couponCode]);
                $session->setConsumedCoupons($consumedCoupons);
                error_log('Coupon removed from session');
            } else {
                error_log('Coupon not found in session');
            }

            error_log('Consumed coupons after removal: ' . json_encode($consumedCoupons));

            // Recalculate cart without coupon
            $cart = $this->cartFacade->getOrCreateFromSession();
            error_log('Cart ID: ' . $cart->getId());
            error_log('Cart discount before recalculate: ' . $cart->getDiscount());

            $this->cartFacade->recalculatePostage($cart);

            // Reset discount to 0 since coupon is removed
            $cart->setDiscount(0);
            $cart->save();

            error_log('Cart discount after recalculate: ' . $cart->getDiscount());

            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('Promo code removed')
            ]);

        } catch (\Exception $e) {
            error_log($e->getTraceAsString());
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
