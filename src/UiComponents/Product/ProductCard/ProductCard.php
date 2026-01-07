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

    public function hasPromo(): bool
    {
        $pse = $this->getDefaultPse();
        $price = $this->getPrice();

        if (!$pse || !$price) {
            return false;
        }

        return ($pse['promo'] ?? false) && ($price['promoPrice'] ?? 0) > 0;
    }

    public function getPromoPercentage(): ?int
    {
        if (!$this->hasPromo()) {
            return null;
        }

        $price = $this->getPrice();
        $regular = $price['price'] ?? 0;
        $promo = $price['promoPrice'] ?? 0;

        if ($regular <= 0) {
            return null;
        }

        return (int) round((($regular - $promo) / $regular) * 100);
    }

    public function isNew(): bool
    {
        $pse = $this->getDefaultPse();
        return $pse ? ($pse['newness'] ?? false) : false;
    }

    public function getImageUrl(): string
    {
        $image = $this->getFirstImage();

        if (!$image || !isset($image['id'])) {
            return '';
        }

        return sprintf(
            '/legacy-image-library/product_image_%d/full/%%5E*!400,400/0/default.webp',
            $image['id']
        );
    }

    public function getWishlistData(): array
    {
        $price = $this->getPrice();
        $image = $this->getFirstImage();

        return [
            'id' => $this->product['id'] ?? 0,
            'title' => $this->product['i18ns']['title'] ?? '',
            'publicUrl' => $this->product['publicUrl'] ?? '',
            'imageId' => $image['id'] ?? 0,
            'price' => $price['price'] ?? 0,
            'promoPrice' => $this->hasPromo() ? ($price['promoPrice'] ?? 0) : 0,
            'isPromo' => $this->hasPromo(),
        ];
    }

    public function getWishlistAttributesHtml(): string
    {
        $data = $this->getWishlistData();

        $jsonData = json_encode([
            'id' => $data['id'],
            'title' => $data['title'],
            'publicUrl' => $data['publicUrl'],
            'imageId' => $data['imageId'],
            'price' => number_format($data['price'], 2) . ' €',
            'promoPrice' => $data['promoPrice'] > 0 ? number_format($data['promoPrice'], 2) . ' €' : '',
            'isPromo' => $data['isPromo'],
        ], JSON_HEX_QUOT | JSON_HEX_APOS);

        return sprintf(
            'x-data="wishlistButton(%s)" @click.prevent="toggle()" :class="{ \'is-active\': isInWishlist }"',
            htmlspecialchars($jsonData, ENT_QUOTES)
        );
    }
}
