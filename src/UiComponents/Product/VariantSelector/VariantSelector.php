<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Product\VariantSelector;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * LiveComponent for product variant selection.
 *
 * This component handles:
 * - Display of variant options (color, size, etc.)
 * - Color swatches and size buttons
 * - Price and stock updates based on selection
 * - Availability checking for combinations
 *
 * Usage in Twig:
 * {{ component('Moderna:Product:VariantSelector', { productId: product.id }) }}
 */
#[AsLiveComponent(
    name: 'Moderna:Product:VariantSelector',
    template: '@UiComponents/Product/VariantSelector/VariantSelector.html.twig'
)]
class VariantSelector
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * Product ID.
     */
    #[LiveProp]
    public int $productId = 0;

    /**
     * Selected PSE ID.
     */
    #[LiveProp]
    public ?int $selectedPseId = null;

    /**
     * Attribute groups with their values.
     * Structure: [{name, code, values: [{id, title, code, selected, available, colorCode}]}]
     *
     * @var array<int, array>
     */
    #[LiveProp]
    public array $attributeGroups = [];

    /**
     * Currently selected attribute values by group code.
     * Structure: {groupCode: valueId}
     *
     * @var array<string, int>
     */
    #[LiveProp]
    public array $selectedAttributes = [];

    /**
     * Current price based on selection.
     */
    #[LiveProp]
    public float $price = 0;

    /**
     * Promo price if applicable.
     */
    #[LiveProp]
    public ?float $promoPrice = null;

    /**
     * Available stock for current selection.
     */
    #[LiveProp]
    public int $stock = 0;

    /**
     * Whether current selection is on promo.
     */
    #[LiveProp]
    public bool $isPromo = false;

    /**
     * Product reference for current PSE.
     */
    #[LiveProp]
    public string $reference = '';

    /**
     * EAN code for current PSE.
     */
    #[LiveProp]
    public string $ean = '';

    /**
     * All PSE data for availability calculations.
     *
     * @var array<int, array>
     */
    private array $pseData = [];

    public function __construct(
        private readonly Session $session,
    ) {}

    /**
     * Initialize the component by loading product variants.
     */
    public function mount(): void
    {
        if ($this->productId <= 0) {
            return;
        }

        $this->loadProductVariants();
        $this->selectDefaultPse();
    }

    /**
     * Load all PSE and attribute data for the product.
     */
    private function loadProductVariants(): void
    {
        $locale = $this->session->getLang()->getLocale();

        // Get all PSEs for this product
        $pseCollection = ProductSaleElementsQuery::create()
            ->filterByProductId($this->productId)
            ->find();

        if ($pseCollection->isEmpty()) {
            return;
        }

        $this->pseData = [];
        $attributeGroupsData = [];

        foreach ($pseCollection as $pse) {
            $pseInfo = [
                'id' => $pse->getId(),
                'price' => (float) $pse->getPrice(),
                'promoPrice' => $pse->getPromo() ? (float) $pse->getPromoPrice() : null,
                'isPromo' => (bool) $pse->getPromo(),
                'stock' => (int) $pse->getQuantity(),
                'reference' => $pse->getRef() ?? '',
                'ean' => $pse->getEanCode() ?? '',
                'isDefault' => (bool) $pse->getIsDefault(),
                'attributes' => [],
            ];

            // Get attribute combinations for this PSE
            foreach ($pse->getAttributeCombinations() as $combination) {
                $attributeAv = $combination->getAttributeAv();
                if (!$attributeAv) {
                    continue;
                }

                $attribute = $attributeAv->getAttribute();
                if (!$attribute) {
                    continue;
                }

                $attribute->setLocale($locale);
                $attributeAv->setLocale($locale);

                $attrCode = $attribute->getId();
                $attrTitle = $attribute->getTitle();
                $avId = $attributeAv->getId();
                $avTitle = $attributeAv->getTitle();

                // Store attribute value for this PSE
                $pseInfo['attributes'][$attrCode] = $avId;

                // Build attribute groups data
                if (!isset($attributeGroupsData[$attrCode])) {
                    $attributeGroupsData[$attrCode] = [
                        'id' => $attribute->getId(),
                        'name' => $attrTitle,
                        'code' => (string) $attrCode,
                        'values' => [],
                    ];
                }

                if (!isset($attributeGroupsData[$attrCode]['values'][$avId])) {
                    // Check if this is a color attribute (look for hex code in title or code)
                    $colorCode = null;
                    $avCode = $attributeAv->getTitle();
                    if (preg_match('/#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})/', $avCode, $matches)) {
                        $colorCode = $matches[0];
                    }

                    $attributeGroupsData[$attrCode]['values'][$avId] = [
                        'id' => $avId,
                        'title' => $avTitle,
                        'code' => $avCode,
                        'colorCode' => $colorCode,
                        'selected' => false,
                        'available' => true,
                    ];
                }
            }

            $this->pseData[$pse->getId()] = $pseInfo;
        }

        // Convert to indexed arrays for template
        $this->attributeGroups = [];
        foreach ($attributeGroupsData as $group) {
            $group['values'] = array_values($group['values']);
            $this->attributeGroups[] = $group;
        }
    }

    /**
     * Select the default PSE.
     */
    private function selectDefaultPse(): void
    {
        // Find default PSE or first available
        $defaultPse = null;
        foreach ($this->pseData as $pseInfo) {
            if ($pseInfo['isDefault'] && $pseInfo['stock'] > 0) {
                $defaultPse = $pseInfo;
                break;
            }
        }

        // If no default with stock, find first with stock
        if (!$defaultPse) {
            foreach ($this->pseData as $pseInfo) {
                if ($pseInfo['stock'] > 0) {
                    $defaultPse = $pseInfo;
                    break;
                }
            }
        }

        // If still none, use first PSE
        if (!$defaultPse && !empty($this->pseData)) {
            $defaultPse = reset($this->pseData);
        }

        if ($defaultPse) {
            $this->selectedPseId = $defaultPse['id'];
            $this->selectedAttributes = $defaultPse['attributes'];
            $this->updatePriceAndStock($defaultPse);
            $this->updateAttributeSelection();
        }
    }

    /**
     * Select an attribute value.
     */
    #[LiveAction]
    public function selectAttribute(#[LiveArg] string $group, #[LiveArg] int $valueId): void
    {
        $this->selectedAttributes[$group] = $valueId;
        $this->updateSelectedPse();
        $this->updateAvailability();
        $this->updateAttributeSelection();
    }

    /**
     * Find and select the PSE matching current attribute selection.
     */
    private function updateSelectedPse(): void
    {
        foreach ($this->pseData as $pseInfo) {
            $matches = true;
            foreach ($this->selectedAttributes as $groupCode => $valueId) {
                if (!isset($pseInfo['attributes'][$groupCode]) ||
                    $pseInfo['attributes'][$groupCode] !== $valueId) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $this->selectedPseId = $pseInfo['id'];
                $this->updatePriceAndStock($pseInfo);
                return;
            }
        }

        // No exact match found - keep current selection
    }

    /**
     * Update price and stock from PSE info.
     */
    private function updatePriceAndStock(array $pseInfo): void
    {
        $this->price = $pseInfo['price'];
        $this->promoPrice = $pseInfo['promoPrice'];
        $this->isPromo = $pseInfo['isPromo'];
        $this->stock = $pseInfo['stock'];
        $this->reference = $pseInfo['reference'];
        $this->ean = $pseInfo['ean'];
    }

    /**
     * Update which attribute values are available based on current selection.
     */
    private function updateAvailability(): void
    {
        foreach ($this->attributeGroups as &$group) {
            foreach ($group['values'] as &$value) {
                // Check if selecting this value would lead to a valid PSE
                $testSelection = $this->selectedAttributes;
                $testSelection[$group['code']] = $value['id'];

                $value['available'] = $this->isSelectionAvailable($testSelection);
            }
        }
    }

    /**
     * Check if a selection combination is available (has stock).
     */
    private function isSelectionAvailable(array $selection): bool
    {
        foreach ($this->pseData as $pseInfo) {
            $matches = true;
            foreach ($selection as $groupCode => $valueId) {
                if (!isset($pseInfo['attributes'][$groupCode]) ||
                    $pseInfo['attributes'][$groupCode] !== $valueId) {
                    $matches = false;
                    break;
                }
            }

            if ($matches && $pseInfo['stock'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update the selected state in attribute groups.
     */
    private function updateAttributeSelection(): void
    {
        foreach ($this->attributeGroups as &$group) {
            foreach ($group['values'] as &$value) {
                $value['selected'] = isset($this->selectedAttributes[$group['code']]) &&
                    $this->selectedAttributes[$group['code']] === $value['id'];
            }
        }
    }

    /**
     * Get the effective price (promo or regular).
     */
    public function getEffectivePrice(): float
    {
        return $this->isPromo && $this->promoPrice !== null ? $this->promoPrice : $this->price;
    }

    /**
     * Check if the current selection is in stock.
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Check if stock is low (less than 5).
     */
    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= 5;
    }
}
