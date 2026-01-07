<?php

declare(strict_types=1);

namespace ModernaBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Debug subscriber to trace cart cookie creation
 */
class ResponseDebugSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Execute BEFORE ResponseListener (priority > 128)
            KernelEvents::RESPONSE => ['debugCartCookie', 256],
        ];
    }

    public function debugCartCookie(ResponseEvent $event): void
    {
        if (!$event->getRequest()->hasSession(true)) {
            return;
        }

        $session = $event->getRequest()->getSession();
        if (!$session->isStarted()) {
            return;
        }

        $cartUseCookie = $session->get('cart_use_cookie');

        $response = $event->getResponse();
        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            $value = $cookie->getValue() ?? '';
        }
    }
}
