<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Layout\SearchModal;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ProductQuery;

/**
 * LiveComponent for search modal with instant results.
 *
 * This component handles:
 * - Modal open/close state
 * - Live search as user types
 * - Product results display
 * - Recent searches (optional)
 *
 * Usage in Twig:
 * {{ component('Moderna:Layout:SearchModal') }}
 */
#[AsLiveComponent(
    name: 'Moderna:Layout:SearchModal',
    template: '@templates/frontOffice/moderna/components/UiComponents/Layout/SearchModal/SearchModal.html.twig'
)]
class SearchModal
{
    use DefaultActionTrait;

    /**
     * Search query string.
     */
    #[LiveProp(writable: true)]
    public string $query = '';

    /**
     * Search results.
     *
     * @var array<int, array>
     */
    #[LiveProp]
    public array $results = [];

    /**
     * Whether the modal is open.
     */
    #[LiveProp]
    public bool $isOpen = false;

    /**
     * Whether a search is in progress.
     */
    #[LiveProp]
    public bool $isLoading = false;

    /**
     * Maximum number of results to show.
     */
    #[LiveProp]
    public int $maxResults = 8;

    /**
     * Total count of matching products.
     */
    #[LiveProp]
    public int $totalCount = 0;

    /**
     * Recent search queries.
     *
     * @var array<int, string>
     */
    #[LiveProp]
    public array $recentSearches = [];

    /**
     * Popular/suggested searches.
     *
     * @var array<int, string>
     */
    #[LiveProp]
    public array $popularSearches = [];

    public function __construct(
        private readonly Session $session,
    ) {}

    /**
     * Initialize the component.
     */
    public function mount(): void
    {
        // Load popular searches (could be from config or analytics)
        $this->popularSearches = [];

        // Load recent searches from session
        $this->recentSearches = $this->session->get('recent_searches', []);
    }

    /**
     * Perform the search.
     */
    #[LiveAction]
    public function search(): void
    {
        $this->results = [];
        $this->totalCount = 0;

        $query = trim($this->query);

        if (strlen($query) < 2) {
            return;
        }

        $this->isLoading = true;

        try {
            $locale = $this->session->getLang()->getLocale();

            // Build the search query
            $productQuery = ProductQuery::create()
                ->filterByVisible(1)
                ->useProductI18nQuery()
                    ->filterByLocale($locale)
                    ->filterByTitle('%' . $query . '%', \Propel\Runtime\ActiveQuery\Criteria::LIKE)
                ->endUse()
                ->_or()
                ->filterByRef('%' . $query . '%', \Propel\Runtime\ActiveQuery\Criteria::LIKE);

            // Get total count
            $this->totalCount = $productQuery->count();

            // Get limited results
            $products = $productQuery
                ->limit($this->maxResults)
                ->find();

            $this->results = [];
            foreach ($products as $product) {
                $product->setLocale($locale);

                // Get default PSE for price
                $defaultPse = $product->getDefaultSaleElements();
                $price = $defaultPse ? (float) $defaultPse->getPrice() : 0;
                $promoPrice = ($defaultPse && $defaultPse->getPromo())
                    ? (float) $defaultPse->getPromoPrice()
                    : null;

                // Get product image
                $imageUrl = null;
                $productImage = $product->getProductImages()->getFirst();
                if ($productImage) {
                    $imageUrl = '/cache/images/product/' . $productImage->getFile();
                }

                $this->results[] = [
                    'id' => $product->getId(),
                    'title' => $product->getTitle(),
                    'ref' => $product->getRef(),
                    'url' => $product->getUrl($locale),
                    'imageUrl' => $imageUrl,
                    'price' => $price,
                    'promoPrice' => $promoPrice,
                    'isPromo' => $defaultPse && (bool) $defaultPse->getPromo(),
                ];
            }

            // Save to recent searches
            $this->addToRecentSearches($query);
        } catch (\Exception $e) {
            // Log error but don't expose to user
            $this->results = [];
        }

        $this->isLoading = false;
    }

    /**
     * Open the search modal.
     */
    #[LiveAction]
    public function open(): void
    {
        $this->isOpen = true;
    }

    /**
     * Close the search modal and reset state.
     */
    #[LiveAction]
    public function close(): void
    {
        $this->isOpen = false;
        $this->query = '';
        $this->results = [];
        $this->totalCount = 0;
        $this->isLoading = false;
    }

    /**
     * Clear the search query and results.
     */
    #[LiveAction]
    public function clear(): void
    {
        $this->query = '';
        $this->results = [];
        $this->totalCount = 0;
    }

    /**
     * Set query from a recent/popular search and perform search.
     */
    #[LiveAction]
    public function setQuery(string $searchQuery): void
    {
        $this->query = $searchQuery;
        $this->search();
    }

    /**
     * Clear recent searches.
     */
    #[LiveAction]
    public function clearRecentSearches(): void
    {
        $this->recentSearches = [];
        $this->session->set('recent_searches', []);
    }

    /**
     * Add a query to recent searches.
     */
    private function addToRecentSearches(string $query): void
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return;
        }

        // Remove if already exists
        $this->recentSearches = array_filter(
            $this->recentSearches,
            fn ($s) => strtolower($s) !== strtolower($query)
        );

        // Add to beginning
        array_unshift($this->recentSearches, $query);

        // Keep only last 5
        $this->recentSearches = array_slice($this->recentSearches, 0, 5);

        // Save to session
        $this->session->set('recent_searches', $this->recentSearches);
    }

    /**
     * Check if there are results.
     */
    public function hasResults(): bool
    {
        return !empty($this->results);
    }

    /**
     * Check if there are more results than shown.
     */
    public function hasMoreResults(): bool
    {
        return $this->totalCount > count($this->results);
    }

    /**
     * Get the search results page URL.
     */
    public function getSearchPageUrl(): string
    {
        return '/search?q=' . urlencode($this->query);
    }
}
