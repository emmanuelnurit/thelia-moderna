<?php

declare(strict_types=1);

namespace FlexyBundle\EventSubscriber;

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles template installation tasks (database tables, etc.)
 * Runs once on first request if installation hasn't been completed.
 */
class TemplateInstallerSubscriber implements EventSubscriberInterface
{
    private static bool $installed = false;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100], // High priority, runs early
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only run once per process and only on main request
        if (self::$installed || !$event->isMainRequest()) {
            return;
        }

        self::$installed = true;
        $this->installDatabaseTables();
    }

    /**
     * Install required database tables if they don't exist
     */
    private function installDatabaseTables(): void
    {
        try {
            $con = Propel::getServiceContainer()->getWriteConnection('TheliaMain');

            // Check if customer_wishlist table exists
            $stmt = $con->prepare("SHOW TABLES LIKE 'customer_wishlist'");
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                // Table doesn't exist, create it
                $sql = "
                    CREATE TABLE IF NOT EXISTS customer_wishlist (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        customer_id INT NOT NULL,
                        product_id INT NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_customer_product (customer_id, product_id),
                        INDEX idx_customer_id (customer_id),
                        INDEX idx_product_id (product_id),
                        FOREIGN KEY (customer_id) REFERENCES customer(id) ON DELETE CASCADE,
                        FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                $con->exec($sql);
            }
        } catch (\Exception $e) {
            // Log error but don't crash the application
            error_log('Moderna template installation error: ' . $e->getMessage());
        }
    }
}
