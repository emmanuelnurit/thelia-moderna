<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Checkout\PromoCode;

use Moderna\UiComponents\Checkout\CheckoutEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\CouponQuery;

/**
 * LiveComponent for promo code management.
 *
 * This component handles:
 * - Promo code input and validation
 * - Applying discount codes to cart
 * - Displaying applied coupons
 * - Removing coupons
 *
 * Usage in Twig:
 * {{ component('Moderna:Checkout:PromoCode') }}
 */
#[AsLiveComponent(name: 'Moderna:Checkout:PromoCode', template: '@UiComponents/Checkout/PromoCode.html.twig')]
class PromoCode
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * The promo code input value.
     */
    #[LiveProp(writable: true)]
    public string $code = '';

    /**
     * Error message to display.
     */
    #[LiveProp]
    public ?string $error = null;

    /**
     * Success message to display.
     */
    #[LiveProp]
    public ?string $success = null;

    /**
     * List of currently applied coupons.
     *
     * @var array<int, array{id: int, code: string, title: string}>
     */
    #[LiveProp]
    public array $appliedCoupons = [];

    public function __construct(
        private readonly Session $session,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Initialize component by loading applied coupons.
     */
    public function mount(): void
    {
        $this->loadAppliedCoupons();
    }

    /**
     * Load currently applied coupons from order.
     */
    private function loadAppliedCoupons(): void
    {
        $order = $this->session->getOrder();
        $this->appliedCoupons = [];

        if ($order) {
            foreach ($order->getConsumedCoupons() as $couponCode) {
                $coupon = CouponQuery::create()->findOneByCode($couponCode);
                if ($coupon) {
                    $this->appliedCoupons[] = [
                        'id' => $coupon->getId(),
                        'code' => $coupon->getCode(),
                        'title' => $coupon->getTitle(),
                    ];
                }
            }
        }
    }

    /**
     * Apply the entered promo code.
     */
    #[LiveAction]
    public function applyCode(): void
    {
        $this->error = null;
        $this->success = null;

        if (empty(trim($this->code))) {
            $this->error = 'Please enter a promo code';

            return;
        }

        try {
            $event = new CouponConsumeEvent($this->code);
            $this->dispatcher->dispatch($event, TheliaEvents::COUPON_CONSUME);

            $this->success = 'Promo code applied successfully';
            $this->code = '';
            $this->loadAppliedCoupons();
            $this->emit(CheckoutEvents::ADD_PROMO_CODE);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Remove an applied coupon.
     */
    #[LiveAction]
    public function removeCode(#[LiveArg] string $couponCode): void
    {
        $this->error = null;
        $this->success = null;

        try {
            $event = new CouponConsumeEvent($couponCode);
            $this->dispatcher->dispatch($event, TheliaEvents::COUPON_CLEAR_ALL);

            $this->loadAppliedCoupons();
            $this->emit(CheckoutEvents::REMOVE_PROMO_CODE);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Check if there are any applied coupons.
     */
    public function hasAppliedCoupons(): bool
    {
        return !empty($this->appliedCoupons);
    }
}
