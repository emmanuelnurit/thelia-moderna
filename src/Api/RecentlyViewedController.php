<?php

declare(strict_types=1);

namespace ModernaBundle\Api;

use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductImageQuery;

#[Route('/moderna-api/recently-viewed', name: 'moderna_api_recently_viewed_')]
class RecentlyViewedController extends AbstractController
{
    /**
     * Sync local recently viewed with server (merge)
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

            // Add local items to server (update viewed_at if exists)
            foreach ($localItems as $item) {
                $productId = (int) ($item['id'] ?? 0);
                if ($productId > 0) {
                    try {
                        $stmt = $con->prepare(
                            'INSERT INTO customer_recently_viewed (customer_id, product_id, viewed_at)
                             VALUES (?, ?, CURRENT_TIMESTAMP)
                             ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP'
                        );
                        $stmt->execute([$customerId, $productId]);
                    } catch (\Exception $e) {
                        // Ignore errors for individual items
                    }
                }
            }

            // Clean up old items (keep only 50 most recent per customer)
            try {
                $stmt = $con->prepare('
                    DELETE FROM customer_recently_viewed
                    WHERE customer_id = ?
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id
                            FROM customer_recently_viewed
                            WHERE customer_id = ?
                            ORDER BY viewed_at DESC
                            LIMIT 50
                        ) AS keep_items
                    )
                ');
                $stmt->execute([$customerId, $customerId]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }

            // Get all recently viewed items with product details
            $stmt = $con->prepare('
                SELECT crv.product_id
                FROM customer_recently_viewed crv
                WHERE crv.customer_id = ?
                ORDER BY crv.viewed_at DESC
                LIMIT 50
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
     * Track product view (add to recently viewed)
     */
    #[Route('/track', name: 'track', methods: ['POST'])]
    public function track(Request $request): JsonResponse
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

            // Insert or update viewed_at timestamp
            $stmt = $con->prepare(
                'INSERT INTO customer_recently_viewed (customer_id, product_id, viewed_at)
                 VALUES (?, ?, CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$customer->getId(), $productId]);

            // Clean up old items (keep only 50 most recent)
            $customerId = $customer->getId();
            try {
                $stmt = $con->prepare('
                    DELETE FROM customer_recently_viewed
                    WHERE customer_id = ?
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id
                            FROM customer_recently_viewed
                            WHERE customer_id = ?
                            ORDER BY viewed_at DESC
                            LIMIT 50
                        ) AS keep_items
                    )
                ');
                $stmt->execute([$customerId, $customerId]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }

            return new JsonResponse(['success' => true]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to track: ' . $e->getMessage()
            ], 500);
        }
    }
}
