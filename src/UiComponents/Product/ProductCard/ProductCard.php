<?php

declare(strict_types=1);

namespace ModernaBundle\UiComponents\Product\ProductCard;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent(name: 'Moderna:ProductCard')]
class ProductCard
{
    #[ExposeInTemplate]
    public array $product;

    #[ExposeInTemplate]
    public string $variant = 'standard';

    #[ExposeInTemplate]
    public bool $showWishlist = true;

    public function getDefaultPse(): ?array
    {
        if (!isset($this->product['productSaleElements'])) {
            return null;
        }

        $filtered = array_filter(
            $this->product['productSaleElements'],
            fn($pse) => $pse['isDefault'] ?? false
        );

        return !empty($filtered) ? reset($filtered) : null;
    }

    public function getPrice(): ?array
    {
        $pse = $this->getDefaultPse();
        if (!$pse || !isset($pse['productPrices'])) {
            return null;
        }

        return !empty($pse['productPrices']) ? $pse['productPrices'][0] : null;
    }

    public function getFirstImage(): ?array
    {
        if (!isset($this->product['productImages']) || empty($this->product['productImages'])) {
            return null;
        }

        return $this->product['productImages'][0];
    }
}
