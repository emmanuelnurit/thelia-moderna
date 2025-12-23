<?php

declare(strict_types=1);

namespace FlexyBundle\Api;

use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductImageQuery;

#[Route('/moderna-api/wishlist', name: 'moderna_api_wishlist_')]
class WishlistController extends AbstractController
{
    /**
     * Sync local wishlist with server (merge)
     */
    #[Route('/sync', name: 'sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        try {
            $session = $request->getSession();
            $customer = $session->getCustomerUser();

            if (!$customer) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Not authenticated'
                ], 401);
            }

            $customerId = $customer->getId();
            $locale = $request->getLocale() ?: 'en_US';

            // Get local items from request
            $data = json_decode($request->getContent(), true) ?? [];
            $localItems = $data['items'] ?? [];

            $con = Propel::getServiceContainer()->getReadConnection('TheliaMain');

            // Add local items to server (ignore duplicates)
            foreach ($localItems as $item) {
                $productId = (int) ($item['id'] ?? 0);
                if ($productId > 0) {
                    try {
                        $stmt = $con->prepare(
                            'INSERT IGNORE INTO customer_wishlist (customer_id, product_id) VALUES (?, ?)'
                        );
                        $stmt->execute([$customerId, $productId]);
                    } catch (\Exception $e) {
                        // Ignore duplicate errors
                    }
                }
            }

            // Get all wishlist items with product details
            $stmt = $con->prepare('
                SELECT cw.product_id
                FROM customer_wishlist cw
                WHERE cw.customer_id = ?
                ORDER BY cw.created_at DESC
            ');
            $stmt->execute([$customerId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build response with product details
            $items = [];
            foreach ($rows as $row) {
                $product = ProductQuery::create()
                    ->filterById($row['product_id'])
                    ->filterByVisible(1)
                    ->findOne();

                if ($product) {
                    $product->setLocale($locale);

                    // Get product image
                    $image = ProductImageQuery::create()
                        ->filterByProductId($product->getId())
                        ->filterByVisible(1)
                        ->orderByPosition()
                        ->findOne();

                    // Build proper image URL using legacy-image-library format
                    $imageUrl = null;
                    $imageId = null;
                    if ($image) {
                        $imageId = $image->getId();
                        $imageUrl = '/legacy-image-library/product_image_' . $imageId . '/full/%5E*!400,400/0/default.webp';
                    }

                    $items[] = [
                        'id' => $product->getId(),
                        'title' => $product->getTitle(),
                        'ref' => $product->getRef(),
                        'url' => $product->getUrl($locale),
                        'image' => $imageUrl,
                        'imageId' => $imageId,
                    ];
                }
            }

            return new JsonResponse([
                'success' => true,
                'items' => $items
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add product to wishlist
     */
    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $session = $request->getSession();
            $customer = $session->getCustomerUser();

            if (!$customer) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Not authenticated'
                ], 401);
            }

            $data = json_decode($request->getContent(), true) ?? [];
            $productId = (int) ($data['product_id'] ?? 0);

            if ($productId <= 0) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid product ID'
                ], 400);
            }

            $con = Propel::getServiceContainer()->getReadConnection('TheliaMain');
            $stmt = $con->prepare(
                'INSERT IGNORE INTO customer_wishlist (customer_id, product_id) VALUES (?, ?)'
            );
            $stmt->execute([$customer->getId(), $productId]);

            return new JsonResponse(['success' => true]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to add: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove product from wishlist
     */
    #[Route('/remove', name: 'remove', methods: ['POST'])]
    public function remove(Request $request): JsonResponse
    {
        try {
            $session = $request->getSession();
            $customer = $session->getCustomerUser();

            if (!$customer) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Not authenticated'
                ], 401);
            }

            $data = json_decode($request->getContent(), true) ?? [];
            $productId = (int) ($data['product_id'] ?? 0);

            if ($productId <= 0) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid product ID'
                ], 400);
            }

            $con = Propel::getServiceContainer()->getReadConnection('TheliaMain');
            $stmt = $con->prepare(
                'DELETE FROM customer_wishlist WHERE customer_id = ? AND product_id = ?'
            );
            $stmt->execute([$customer->getId(), $productId]);

            return new JsonResponse(['success' => true]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to remove: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all wishlist items
     */
    #[Route('/clear', name: 'clear', methods: ['POST'])]
    public function clear(Request $request): JsonResponse
    {
        try {
            $session = $request->getSession();
            $customer = $session->getCustomerUser();

            if (!$customer) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Not authenticated'
                ], 401);
            }

            $con = Propel::getServiceContainer()->getReadConnection('TheliaMain');
            $stmt = $con->prepare('DELETE FROM customer_wishlist WHERE customer_id = ?');
            $stmt->execute([$customer->getId()]);

            return new JsonResponse(['success' => true]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to clear: ' . $e->getMessage()
            ], 500);
        }
    }
}
