<?php

declare(strict_types=1);

namespace ModernaBundle\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\LangQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

#[Route('/moderna-api/language', name: 'moderna_api_language_')]
class LanguageSwitchController extends AbstractController
{
    private const COOKIE_NAME = 'thelia_user_lang';
    private const COOKIE_LIFETIME = 365 * 24 * 60 * 60; // 1 year

    public function __construct(
        private readonly LangService $langService
    ) {
    }

    /**
     * Switch language and redirect back to referrer (with translated URL if available)
     */
    #[Route('/switch/{locale}', name: 'switch', methods: ['GET'])]
    public function switch(string $locale, Request $request): Response
    {
        // Find the language by locale
        $lang = LangQuery::create()
            ->filterByActive(1)
            ->filterByLocale($locale)
            ->findOne();

        if (!$lang) {
            // Try by code
            $lang = LangQuery::create()
                ->filterByActive(1)
                ->filterByCode($locale)
                ->findOne();
        }

        // Get the referrer URL or fallback to homepage
        $referrer = $request->headers->get('referer');
        $redirectUrl = '/';

        if ($referrer) {
            // Parse the referrer
            $parsedUrl = parse_url($referrer);
            $path = $parsedUrl['path'] ?? '/';

            // Try to find the translated URL for this path
            $translatedPath = $this->getTranslatedUrl($path, $lang?->getLocale());

            if ($translatedPath) {
                $redirectUrl = $translatedPath;
            } else {
                $redirectUrl = $path;
            }

            // Parse and filter query parameters
            $queryParams = [];
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                unset($queryParams['lang']);
            }

            // Append query params if any
            if (!empty($queryParams)) {
                $redirectUrl .= '?' . http_build_query($queryParams);
            }
        }

        $response = new RedirectResponse($redirectUrl);

        if ($lang) {
            // Set the language using Thelia's LangService
            $this->langService->setLang($lang);

            // Set the cookie to persist the language preference
            $response->headers->setCookie(
                new Cookie(
                    self::COOKIE_NAME,
                    $lang->getLocale(),
                    time() + self::COOKIE_LIFETIME,
                    '/',
                    null,
                    false,
                    false,
                    false,
                    Cookie::SAMESITE_LAX
                )
            );
        }

        return $response;
    }

    /**
     * Find the translated URL for a given path and target locale
     */
    private function getTranslatedUrl(string $path, ?string $targetLocale): ?string
    {
        if (!$targetLocale) {
            return null;
        }

        // Remove leading slash for comparison
        $urlPath = ltrim($path, '/');

        if (empty($urlPath)) {
            return '/';
        }

        // Find the current URL in the rewriting table
        $currentRewrite = RewritingUrlQuery::create()
            ->filterByUrl($urlPath)
            ->findOne();

        if (!$currentRewrite) {
            return null;
        }

        // Find the equivalent URL in the target locale
        $targetRewrite = RewritingUrlQuery::create()
            ->filterByView($currentRewrite->getView())
            ->filterByViewId($currentRewrite->getViewId())
            ->filterByViewLocale($targetLocale)
            ->filterByRedirected(null)
            ->findOne();

        if ($targetRewrite) {
            return '/' . $targetRewrite->getUrl();
        }

        return null;
    }
}
