<?php

declare(strict_types=1);

namespace ModernaBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Persists user language preference in a cookie.
 *
 * The cookie is the "source of truth" for user's language preference.
 * It is only updated when:
 * - User explicitly changes language via ?lang= URL parameter
 * - No cookie exists yet (first visit)
 *
 * On each request, if the session language differs from the cookie,
 * we restore the session from the cookie (to handle cases where
 * internal processes like MailerFactory temporarily change the session).
 */
class LanguagePersistenceSubscriber implements EventSubscriberInterface
{
    private const COOKIE_NAME = 'thelia_user_lang';
    private const COOKIE_LIFETIME = 365 * 24 * 60 * 60; // 1 year

    /** @var bool Flag to track if user explicitly changed language */
    private bool $userChangedLanguage = false;

    /** @var bool Flag to track if we need to set initial cookie */
    private bool $needsInitialCookie = false;

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority 64: run AFTER KernelListener (128) to override language from cookie
            // Higher priority = runs first, so 64 < 128 means we run after
            KernelEvents::REQUEST => ['onKernelRequest', 64],
            // Priority -10: run after everything to save language state
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    /**
     * On request: restore language from cookie, overriding Thelia's default.
     * This runs AFTER Thelia's KernelListener (priority 64 < 128).
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // Only handle main request
        if (!$event->isMainRequest()) {
            return;
        }

        // Skip admin environment
        if (Request::$isAdminEnv) {
            return;
        }

        $request = $event->getRequest();

        // Check if user is explicitly changing language via URL parameter
        // In this case, let Thelia handle it and we'll save the new lang in response
        if ($request->query->has('lang') || $request->query->has('locale')) {
            $this->userChangedLanguage = true;
            return;
        }

        // Check if cookie exists
        $savedLocale = $request->cookies->get(self::COOKIE_NAME);

        if (empty($savedLocale)) {
            // No cookie yet - we'll set it in onKernelResponse with current session language
            $this->needsInitialCookie = true;
            return;
        }

        // Get session (should be available now since we run after KernelListener)
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if (!$session instanceof Session) {
            return;
        }

        // Find language from cookie
        $langFromCookie = LangQuery::create()
            ->filterByActive(true)
            ->filterByLocale($savedLocale)
            ->findOne();

        if (!$langFromCookie instanceof Lang) {
            return;
        }

        // Check if session lang matches cookie lang
        $sessionLang = $session->getLang(false);

        // Override session language with cookie value (cookie is source of truth)
        if (!$sessionLang instanceof Lang || $sessionLang->getLocale() !== $langFromCookie->getLocale()) {
            $session->setLang($langFromCookie);
            // Sync Symfony's request locale for |trans filter
            $request->setLocale($langFromCookie->getLocale());
        }
    }

    /**
     * On response: save language to cookie only when appropriate.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        // Only handle main request
        if (!$event->isMainRequest()) {
            return;
        }

        // Skip admin environment
        if (Request::$isAdminEnv) {
            return;
        }

        // Only update cookie if:
        // 1. User explicitly changed language via URL parameter
        // 2. No cookie exists yet (first visit)
        if (!$this->userChangedLanguage && !$this->needsInitialCookie) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if (!$session instanceof Session) {
            return;
        }

        // Get current language from session
        $currentLang = $session->getLang(true);
        if (!$currentLang instanceof Lang) {
            return;
        }

        $currentLocale = $currentLang->getLocale();
        if (empty($currentLocale)) {
            return;
        }

        // Set cookie with current language
        $response = $event->getResponse();
        $response->headers->setCookie(
            new Cookie(
                self::COOKIE_NAME,
                $currentLocale,
                time() + self::COOKIE_LIFETIME,
                '/',
                null,
                false,
                false,
                false,
                Cookie::SAMESITE_LAX
            )
        );

        // Reset flags
        $this->userChangedLanguage = false;
        $this->needsInitialCookie = false;
    }
}
