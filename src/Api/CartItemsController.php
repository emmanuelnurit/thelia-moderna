<?php

declare(strict_types=1);

namespace Moderna\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\ProductImageQuery;

#[Route('/moderna-api', name: 'moderna_api_')]
class CartItemsController extends AbstractController
{
    #[Route('/cart_items', name: 'cart_items', methods: ['GET'])]
    public function getCartItems(Request $request): JsonResponse
    {
        try {
            $cart = null;

            // First, try to get cart from session (this is set after login with customer's cart)
            $session = $request->getSession();
            $sessionCartId = $session->get('thelia.cart_id');

            if ($sessionCartId) {
                $cart = CartQuery::create()->findPk($sessionCartId);
            }

            // If no cart from session, try cookie token (for anonymous users)
            if (!$cart) {
                $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
                $cookieToken = $request->cookies->get($cookieName);

                if ($cookieToken) {
                    $cart = CartQuery::create()->findOneByToken($cookieToken);
                }
            }

            if (!$cart) {
                // No cart found, return empty
                return new JsonResponse([
                    'cart_items' => [],
                    'total' => 0,
                    'count' => 0
                ]);
            }

            // Build cart items response
            $items = [];
            $total = 0;
            $count = 0;

            // Get default country for tax calculation
            $country = CountryQuery::create()->findOneByByDefault(1);
            $state = null;

            foreach ($cart->getCartItems() as $cartItem) {
                $pse = $cartItem->getProductSaleElements();
                $product = $pse->getProduct();
                $productId = $product->getId();

                // Get PSE image - use PSE index to match product image
                $imageId = null;
                $productImages = ProductImageQuery::create()
                    ->filterByProductId($productId)
                    ->orderByPosition('ASC')
                    ->find();

                if ($productImages->count() > 0) {
                    // Get PSE index within product
                    $pseIndex = 0;
                    foreach ($product->getProductSaleElementss() as $idx => $productPse) {
                        if ($productPse->getId() === $pse->getId()) {
                            $pseIndex = $idx;
                            break;
                        }
                    }
                    // Use modulo to cycle through images
                    $imageIndex = $pseIndex % $productImages->count();
                    $imageId = $productImages->offsetGet($imageIndex)->getId();
                }

                $items[] = [
                    'id' => $cartItem->getId(),
                    'product_id' => $productId,
                    'product_sale_elements_id' => $pse->getId(),
                    'quantity' => $cartItem->getQuantity(),
                    'price' => $cartItem->getPrice(),
                    'promo_price' => $cartItem->getPromoPrice(),
                    'taxedPrice' => $cartItem->getTaxedPrice($country, $state),
                    'taxedPromoPrice' => $cartItem->getTaxedPromoPrice($country, $state),
                    'promo' => $cartItem->getPromo(),
                    'title' => $product->getTitle(),
                    'imageId' => $imageId,
                ];

                $count += $cartItem->getQuantity();
                $total += ($cartItem->getPromo() ? $cartItem->getPromoPrice() : $cartItem->getPrice()) * $cartItem->getQuantity();
            }


            return new JsonResponse([
                'cart_items' => $items,
                'total' => $total,
                'count' => $count,
                'cart_id' => $cart->getId(),
                'cart_token' => $cart->getToken()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'cart_items' => [],
                'total' => 0,
                'count' => 0
            ], 500);
        }
    }
}
