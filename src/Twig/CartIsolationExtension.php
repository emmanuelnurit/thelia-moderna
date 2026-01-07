<?php

declare(strict_types=1);

namespace ModernaBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;

/**
 * Twig extension to get cart items by cookie token instead of session
 * Fixes shared cart bug in PHP-FPM workers
 */
class CartIsolationExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CouponManager $couponManager
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_cart_items_by_cookie', [$this, 'getCartItemsByCookie']),
            new TwigFunction('get_cart_count_by_cookie', [$this, 'getCartCountByCookie']),
            new TwigFunction('get_cart_discount_by_cookie', [$this, 'getCartDiscountByCookie']),
        ];
    }

    public function getCartItemsByCookie(): array
    {
        try {
            $request = $this->requestStack->getMainRequest();
            if (!$request) {
                return [];
            }

            // Get cart by cookie token
            $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
            $cookieToken = $request->cookies->get($cookieName);

            if (!$cookieToken) {
                return [];
            }

            $cart = CartQuery::create()->findOneByToken($cookieToken);
            if (!$cart) {
                return [];
            }

            // Build items array compatible with OpenApi format
            $items = [];

            // Get country for tax calculation
            $country = \Thelia\Model\Country::getShopLocation();
            $state = null;

            // Get current locale from session for proper translations
            $session = $request->getSession();
            $locale = $session->getLang()?->getLocale() ?? 'en_US';

            // Get all product images indexed by product ID for efficient lookup
            $productImagesCache = [];

            foreach ($cart->getCartItems() as $cartItem) {
                $pse = $cartItem->getProductSaleElements();
                $product = $pse->getProduct();
                $productId = $product->getId();

                // Get attribute combinations (color, size, etc.)
                $attributeCombinations = [];
                foreach ($pse->getAttributeCombinations() as $attrCombination) {
                    $attribute = $attrCombination->getAttribute();
                    $attributeAv = $attrCombination->getAttributeAv();
                    if ($attributeAv) {
                        // Set locale for proper translations
                        $attribute->setLocale($locale);
                        $attributeAv->setLocale($locale);
                        $attributeCombinations[] = [
                            'attributeTitle' => $attribute->getTitle(),
                            'title' => $attributeAv->getTitle(),
                        ];
                    }
                }

                // Calculate image for this PSE (same logic as CartController)
                $imageId = null;

                // Cache product images
                if (!isset($productImagesCache[$productId])) {
                    $productImagesCache[$productId] = [];
                    foreach ($product->getProductImages() as $productImage) {
                        if ($productImage->getVisible()) {
                            $productImagesCache[$productId][] = $productImage->getId();
                        }
                    }
                }

                $productImages = $productImagesCache[$productId];

                // Get images associated with this PSE
                $pseImages = [];
                foreach ($pse->getProductSaleElementsProductImages() as $pseProductImage) {
                    $productImage = $pseProductImage->getProductImage();
                    if ($productImage && $productImage->getVisible()) {
                        $pseImages[] = $productImage->getId();
                    }
                }

                // Fallback: assign different images based on PSE position
                if (empty($pseImages) && !empty($productImages)) {
                    // Find PSE index
                    $pseIndex = 0;
                    foreach ($product->getProductSaleElementss() as $productPse) {
                        if ($productPse->getId() === $pse->getId()) {
                            break;
                        }
                        $pseIndex++;
                    }
                    $imageIndex = $pseIndex % count($productImages);
                    $imageId = $productImages[$imageIndex];
                } elseif (!empty($pseImages)) {
                    $imageId = $pseImages[0];
                }

                $items[] = [
                    'id' => $cartItem->getId(),
                    'productId' => $productId,
                    'productSaleElementsId' => $pse->getId(),
                    'quantity' => $cartItem->getQuantity(),
                    'price' => $cartItem->getPrice(),
                    'promoPrice' => $cartItem->getPromoPrice(),
                    'taxedPrice' => $cartItem->getTaxedPrice($country, $state),
                    'taxedPromoPrice' => $cartItem->getTaxedPromoPrice($country, $state),
                    'promo' => $cartItem->getPromo(),
                    'title' => $product->getTitle(),
                    'imageId' => $imageId, // Image ID for this PSE
                    // Use API-compatible URL format
                    'product' => '/api/front/products/' . $productId,
                    'productSaleElements' => (object)[
                        'id' => $pse->getId(),
                        'ref' => $pse->getRef(),
                        'ean' => $pse->getEanCode(),
                        'quantity' => $pse->getQuantity(),
                        'promo' => $pse->getPromo(),
                        'attributeCombinations' => $attributeCombinations,
                    ],
                ];
            }


            return $items;

        } catch (\Exception $e) {
            return [];
        }
    }

    public function getCartCountByCookie(): int
    {
        try {
            $request = $this->requestStack->getMainRequest();
            if (!$request) {
                return 0;
            }

            // Get cart by cookie token
            $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
            $cookieToken = $request->cookies->get($cookieName);

            if (!$cookieToken) {
                return 0;
            }

            $cart = CartQuery::create()->findOneByToken($cookieToken);
            if (!$cart) {
                return 0;
            }

            // Calculate total quantity
            $count = 0;
            foreach ($cart->getCartItems() as $cartItem) {
                $count += $cartItem->getQuantity();
            }

            return (int) $count;

        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getCartDiscountByCookie(): array
    {
        try {
            $request = $this->requestStack->getMainRequest();
            if (!$request) {
                return ['discount' => 0, 'coupons' => []];
            }

            // Get cart by cookie token
            $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
            $cookieToken = $request->cookies->get($cookieName);

            if (!$cookieToken) {
                return ['discount' => 0, 'coupons' => []];
            }

            $cart = CartQuery::create()->findOneByToken($cookieToken);
            if (!$cart) {
                return ['discount' => 0, 'coupons' => []];
            }

            // Get discount amount dynamically from CouponManager (recalculates based on current cart)
            $discount = $this->couponManager->getDiscount();

            // Get consumed coupons from session
            $coupons = [];
            $session = $request->getSession();
            if ($session) {
                $consumedCoupons = $session->getConsumedCoupons();
                if (is_array($consumedCoupons)) {
                    // Extract coupon codes (keys of the array)
                    $coupons = array_keys($consumedCoupons);
                }
            }


            return [
                'discount' => $discount,
                'coupons' => $coupons
            ];

        } catch (\Exception $e) {
            return ['discount' => 0, 'coupons' => []];
        }
    }
}
