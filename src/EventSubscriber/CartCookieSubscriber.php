<?php

declare(strict_types=1);

namespace Moderna\EventSubscriber;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ConfigQuery;

/**
 * Force cart cookie creation based on cart token from session
 * Fixes shared cart bug in PHP-FPM workers
 */
class CartCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Execute AFTER ResponseListener (priority < 128)
            KernelEvents::RESPONSE => ['forceCartCookie', 64],
        ];
    }

    public function forceCartCookie(ResponseEvent $event): void
    {
        if (!$event->getRequest()->hasSession(true)) {
            return;
        }

        $session = $event->getRequest()->getSession();
        if (!$session instanceof Session || !$session->isStarted()) {
            return;
        }

        // Get cart from session
        try {
            $cart = $session->getSessionCart($this->eventDispatcher);

            if (!$cart || !$cart->getToken()) {
                return;
            }

            $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
            $response = $event->getResponse();

            // Check if cookie already exists
            $existingCookie = $event->getRequest()->cookies->get($cookieName);

            if ($existingCookie === $cart->getToken()) {
                // Cookie already correct
                return;
            }

            // Create/update cookie with cart token
            $response->headers->setCookie(
                new Cookie(
                    $cookieName,
                    $cart->getToken(),
                    time() + ConfigQuery::read('cart.cookie_lifetime', 60 * 60 * 24 * 365),
                    '/',
                    null,
                    false,
                    true
                )
            );


        } catch (\Exception $e) {
        }
    }
}
