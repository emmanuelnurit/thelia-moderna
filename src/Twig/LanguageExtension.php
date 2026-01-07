<?php

declare(strict_types=1);

namespace ModernaBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ConfigQuery;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Provides language-related global variables for Twig templates
 */
class LanguageExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request?->getSession();

        // Get current language from session
        $lang = null;
        $locale = 'en_US';
        $langCode = 'en';

        if ($session instanceof Session) {
            $lang = $session->getLang(true);
            if ($lang) {
                $locale = $lang->getLocale();
                // Extract language code from locale (e.g., 'fr' from 'fr_FR')
                $langCode = substr($locale, 0, 2);
            }
        }

        // Get store name and URL from Thelia config
        $storeName = ConfigQuery::read('store_name', 'Thelia');
        $storeUrl = ConfigQuery::read('url_site', '');

        return [
            'lang_code' => $langCode,
            'locale' => $locale,
            'store_name' => $storeName,
            'store_url' => $storeUrl,
        ];
    }
}
