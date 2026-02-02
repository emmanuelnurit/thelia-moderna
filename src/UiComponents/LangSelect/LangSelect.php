<?php

declare(strict_types=1);

namespace Moderna\UiComponents\LangSelect;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ConfigQuery;

#[AsTwigComponent(name: 'Moderna:LangSelect', template: '@UiComponents/LangSelect/LangSelect.html.twig')]
class LangSelect
{
    #[ExposeInTemplate()]
    public ?array $currentLang = null;

    #[ExposeInTemplate()]
    public array $langs = [];

    #[ExposeInTemplate()]
    public string $baseUrl = '';

    public function __construct(
        private readonly DataAccessService $dataAccessService,
        private readonly Session $session
    ) {
    }

    public function getLangs(): array
    {
        if (empty($this->langs)) {
            $this->langs = $this->dataAccessService->resources('/api/front/languages', [
                'active' => true,
            ]);
        }

        return $this->langs;
    }

    public function getCurrentLang(): ?array
    {
        $langs = $this->getLangs();

        // Don't show selector if only one language
        if (\count($langs) <= 1) {
            return null;
        }

        $sessionLangId = $this->session->getLang()?->getId();

        $filters = array_filter($langs, fn ($lang) => $lang['id'] == $sessionLangId);

        $this->currentLang = reset($filters) ?: null;

        return $this->currentLang;
    }

    public function getBaseUrl(): string
    {
        if (empty($this->baseUrl)) {
            $this->baseUrl = ConfigQuery::read('url_site', '');
        }

        return $this->baseUrl;
    }
}
