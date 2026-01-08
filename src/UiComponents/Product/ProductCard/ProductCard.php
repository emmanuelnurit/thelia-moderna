<?php

declare(strict_types=1);

namespace ModernaBundle\UiComponents\Product\ProductCard;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Country;
use Thelia\Domain\Taxation\TaxEngine\Calculator;

#[AsTwigComponent(name: 'Moderna:Product:ProductCard')]
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

    private function getTaxedPrice(int $pseId, float $price): float
    {
        try {
            $pse = ProductSaleElementsQuery::create()->findPk($pseId);
            if (!$pse || !$pse->getProduct()) {
                return $price;
            }

            $country = Country::getShopLocation();
            $taxCalculator = (new Calculator())->load($pse->getProduct(), $country);

            return round($taxCalculator->getTaxedPrice($price), 2);
        } catch (\Exception $e) {
            return $price;
        }
    }

    public function getWishlistData(): array
    {
        $price = $this->getPrice();
        $image = $this->getFirstImage();
        $pse = $this->getDefaultPse();

        // Calculate taxed prices (TTC)
        $taxedPrice = 0;
        $taxedPromoPrice = 0;

        if ($pse && $price) {
            $pseId = $pse['id'] ?? 0;
            $taxedPrice = $this->getTaxedPrice($pseId, $price['price'] ?? 0);
            $taxedPromoPrice = $this->hasPromo() ? $this->getTaxedPrice($pseId, $price['promoPrice'] ?? 0) : 0;
        }

        return [
            'id' => $this->product['id'] ?? 0,
            'title' => $this->product['i18ns']['title'] ?? '',
            'publicUrl' => $this->product['publicUrl'] ?? '',
            'imageId' => $image['id'] ?? 0,
            'price' => $taxedPrice,
            'promoPrice' => $taxedPromoPrice,
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
            'price' => number_format($data['price'], 2, ',', ' ') . ' €',
            'promoPrice' => $data['promoPrice'] > 0 ? number_format($data['promoPrice'], 2, ',', ' ') . ' €' : '',
            'isPromo' => $data['isPromo'],
        ], JSON_HEX_QUOT | JSON_HEX_APOS);

        return sprintf(
            'x-data="wishlistButton(%s)" @click.prevent="toggle()" :class="{ \'is-active\': isInWishlist }"',
            htmlspecialchars($jsonData, ENT_QUOTES)
        );
    }

    #[ExposeInTemplate]
    public function getTaxedPriceFormatted(): string
    {
        $price = $this->getPrice();
        $pse = $this->getDefaultPse();

        if (!$pse || !$price) {
            return '0,00 €';
        }

        $pseId = $pse['id'] ?? 0;
        $taxedPrice = $this->getTaxedPrice($pseId, $price['price'] ?? 0);

        return number_format($taxedPrice, 2, ',', ' ') . ' €';
    }

    #[ExposeInTemplate]
    public function getTaxedPromoPriceFormatted(): string
    {
        if (!$this->hasPromo()) {
            return '';
        }

        $price = $this->getPrice();
        $pse = $this->getDefaultPse();

        if (!$pse || !$price) {
            return '';
        }

        $pseId = $pse['id'] ?? 0;
        $taxedPromoPrice = $this->getTaxedPrice($pseId, $price['promoPrice'] ?? 0);

        return number_format($taxedPromoPrice, 2, ',', ' ') . ' €';
    }

    #[ExposeInTemplate]
    public function getTaxedPromoPercentage(): ?int
    {
        if (!$this->hasPromo()) {
            return null;
        }

        $price = $this->getPrice();
        $pse = $this->getDefaultPse();

        if (!$pse || !$price) {
            return null;
        }

        $pseId = $pse['id'] ?? 0;
        $taxedPrice = $this->getTaxedPrice($pseId, $price['price'] ?? 0);
        $taxedPromoPrice = $this->getTaxedPrice($pseId, $price['promoPrice'] ?? 0);

        if ($taxedPrice <= 0) {
            return null;
        }

        return (int) round((($taxedPrice - $taxedPromoPrice) / $taxedPrice) * 100);
    }
}
