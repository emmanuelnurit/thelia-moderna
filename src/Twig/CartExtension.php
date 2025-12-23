<?php

declare(strict_types=1);

namespace FlexyBundle\Twig;

use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CartExtension extends AbstractExtension
{
    public function __construct(
        private readonly CartFacade $cartFacade,
        private readonly CouponManager $couponManager
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_cart_discount', [$this, 'getCartDiscount']),
        ];
    }

    public function getCartDiscount(): float
    {
        try {
            // Use CouponManager to calculate discount from consumed coupons
            return $this->couponManager->getDiscount();
        } catch (\Throwable $e) {
            // Silently fail and return 0
            return 0.0;
        }
    }
}
