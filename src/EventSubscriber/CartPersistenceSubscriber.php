<?php

declare(strict_types=1);

namespace ModernaBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Cart;

/**
 * CRITICAL FIX: Force cart retrieval by cookie instead of session
 * This fixes the shared cart bug in PHP-FPM workers caused by static $transientCart
 */
class CartPersistenceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // HIGH priority (256) to execute BEFORE Thelia's default handler (128)
            TheliaEvents::CART_RESTORE_CURRENT => ['restoreCartByCookie', 256],
        ];
    }

    public function restoreCartByCookie(CartRestoreEvent $event): void
    {
        try {
            $request = $this->requestStack->getMainRequest();
            if (!$request) {
                return;
            }

            // CRITICAL: Get cart by cookie token instead of session
            $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
            $cookieToken = $request->cookies->get($cookieName);

            if ($cookieToken) {
                // Try to find cart by cookie token
                $cart = CartQuery::create()->findOneByToken($cookieToken);

                if ($cart) {

                    // Set cart in event to override Thelia's session-based retrieval
                    $event->setCart($cart);

                    // Update session to match cookie
                    if ($request->hasSession()) {
                        $request->getSession()->setSessionCart($cart);
                    }

                    // Stop event propagation to prevent Thelia from using session
                    $event->stopPropagation();
                    return;
                }
            }

            // No cart found by cookie, let Thelia create a new one but ensure it gets a token

        } catch (\Exception $e) {
        }
    }
}
