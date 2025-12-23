<?php

declare(strict_types=1);

namespace FlexyBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Country;
use Thelia\Domain\Taxation\TaxEngine\Calculator;

/**
 * Twig extension to calculate taxed prices without modifying core API
 */
class TaxedPriceExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_taxed_price', [$this, 'getTaxedPrice']),
            new TwigFunction('get_taxed_promo_price', [$this, 'getTaxedPromoPrice']),
        ];
    }

    public function getTaxedPrice(int $pseId, float $price): ?float
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

    public function getTaxedPromoPrice(int $pseId, float $promoPrice): ?float
    {
        try {
            $pse = ProductSaleElementsQuery::create()->findPk($pseId);
            if (!$pse || !$pse->getProduct()) {
                return $promoPrice;
            }

            $country = Country::getShopLocation();
            $taxCalculator = (new Calculator())->load($pse->getProduct(), $country);

            return round($taxCalculator->getTaxedPrice($promoPrice), 2);
        } catch (\Exception $e) {
            return $promoPrice;
        }
    }
}
