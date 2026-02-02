<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Checkout\Summary;

use Moderna\DTO\CartItemDto;
use Moderna\UiComponents\Checkout\CheckoutEvents;
use Psr\Log\LoggerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\CouponQuery;
use Thelia\Model\ModuleQuery;

/**
 * LiveComponent for order summary display.
 *
 * This component handles:
 * - Display of cart items summary
 * - Subtotal, shipping, discount, tax calculation
 * - Real-time updates from other checkout components
 * - Display of applied coupons
 *
 * Usage in Twig:
 * {{ component('Moderna:Checkout:Summary') }}
 */
#[AsLiveComponent(
    name: 'Moderna:Checkout:Summary',
    template: '@UiComponents/Checkout/Summary/Summary.html.twig'
)]
class Summary
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * Cart items for display.
     *
     * @var array<int, array>
     */
    #[LiveProp]
    public array $items = [];

    /**
     * Subtotal before shipping and discounts.
     */
    #[LiveProp]
    public float $subtotal = 0;

    /**
     * Shipping cost.
     */
    #[LiveProp]
    public float $shipping = 0;

    /**
     * Discount amount.
     */
    #[LiveProp]
    public float $discount = 0;

    /**
     * Tax amount.
     */
    #[LiveProp]
    public float $tax = 0;

    /**
     * Total amount.
     */
    #[LiveProp]
    public float $total = 0;

    /**
     * Total items count.
     */
    #[LiveProp]
    public int $itemCount = 0;

    /**
     * Selected delivery module name.
     */
    #[LiveProp]
    public ?string $deliveryModuleName = null;

    /**
     * Selected payment module name.
     */
    #[LiveProp]
    public ?string $paymentModuleName = null;

    /**
     * Applied coupons list.
     *
     * @var array<int, array{code: string, title: string}>
     */
    #[LiveProp]
    public array $appliedCoupons = [];

    /**
     * Currency code.
     */
    #[LiveProp]
    public string $currencyCode = 'EUR';

    public function __construct(
        private readonly Session $session,
        private readonly AttributeAccessService $attributeAccessService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Initialize component by loading summary data.
     */
    public function mount(): void
    {
        $this->loadSummary();
    }

    /**
     * Load complete summary data.
     */
    private function loadSummary(): void
    {
        try {
            $this->loadCartItems();
            $this->loadTotals();
            $this->loadCoupons();
            $this->loadModuleNames();
            $this->loadCurrency();
        } catch (\Exception $e) {
            $this->logger->error('Error loading summary: '.$e->getMessage());
        }
    }

    /**
     * Load cart items.
     */
    private function loadCartItems(): void
    {
        $cart = $this->session->getSessionCart();
        if (!$cart) {
            $this->items = [];
            $this->itemCount = 0;

            return;
        }

        $this->items = [];
        $locale = $this->session->getLang()->getLocale();

        foreach ($cart->getCartItems() as $cartItem) {
            $dto = CartItemDto::fromCartItem($cartItem, $locale);
            $this->items[] = $dto->toArray();
        }

        $this->itemCount = (int) $this->attributeAccessService->attributeCart('item_count');
    }

    /**
     * Load price totals.
     */
    private function loadTotals(): void
    {
        $this->subtotal = (float) ($this->attributeAccessService->attributeCart('raw_taxed_total_price') ?? 0);
        $this->total = (float) ($this->attributeAccessService->attributeCart('total_taxed_price') ?? 0);
        $this->tax = (float) ($this->attributeAccessService->attributeCart('total_tax_amount') ?? 0);
        $this->shipping = (float) ($this->attributeAccessService->attributeCart('taxed_postage') ?? 0);
        $this->discount = (float) ($this->attributeAccessService->attributeCart('taxed_discount') ?? 0);
    }

    /**
     * Load applied coupons.
     */
    private function loadCoupons(): void
    {
        $order = $this->session->getOrder();
        $this->appliedCoupons = [];

        if ($order) {
            foreach ($order->getConsumedCoupons() as $couponCode) {
                $coupon = CouponQuery::create()->findOneByCode($couponCode);
                if ($coupon) {
                    $this->appliedCoupons[] = [
                        'code' => $coupon->getCode(),
                        'title' => $coupon->getTitle(),
                    ];
                }
            }
        }
    }

    /**
     * Load selected module names.
     */
    private function loadModuleNames(): void
    {
        $cart = $this->session->getSessionCart();
        if (!$cart) {
            return;
        }

        $locale = $this->session->getLang()->getLocale();

        // Load delivery module name
        $deliveryModuleId = $cart->getDeliveryModuleId();
        if ($deliveryModuleId) {
            $module = ModuleQuery::create()->findPk($deliveryModuleId);
            if ($module) {
                $module->setLocale($locale);
                $this->deliveryModuleName = $module->getTitle();
            }
        }

        // Load payment module name
        $paymentModuleId = $cart->getPaymentModuleId();
        if ($paymentModuleId) {
            $module = ModuleQuery::create()->findPk($paymentModuleId);
            if ($module) {
                $module->setLocale($locale);
                $this->paymentModuleName = $module->getTitle();
            }
        }
    }

    /**
     * Load currency information.
     */
    private function loadCurrency(): void
    {
        $currency = $this->session->getCurrency();
        if ($currency) {
            $this->currencyCode = $currency->getCode();
        }
    }

    /**
     * Handle delivery module change event.
     */
    #[LiveListener(CheckoutEvents::SET_DELIVERY_MODULE_ID)]
    public function onDeliveryModuleChanged(): void
    {
        $this->loadSummary();
    }

    /**
     * Handle payment module change event.
     */
    #[LiveListener(CheckoutEvents::SET_PAYMENT_MODULE_ID)]
    public function onPaymentModuleChanged(): void
    {
        $this->loadSummary();
    }

    /**
     * Handle promo code added event.
     */
    #[LiveListener(CheckoutEvents::ADD_PROMO_CODE)]
    public function onPromoCodeAdded(): void
    {
        $this->loadSummary();
    }

    /**
     * Handle promo code removed event.
     */
    #[LiveListener(CheckoutEvents::REMOVE_PROMO_CODE)]
    public function onPromoCodeRemoved(): void
    {
        $this->loadSummary();
    }

    /**
     * Handle cart updated event.
     */
    #[LiveListener(CheckoutEvents::CART_UPDATED_EVENT)]
    public function onCartUpdated(): void
    {
        $this->loadSummary();
    }

    /**
     * Handle generic sync summary event.
     */
    #[LiveListener(CheckoutEvents::SYNC_SUMMARY)]
    #[LiveListener('syncSummary')]
    public function onSyncSummary(): void
    {
        $this->loadSummary();
    }

    /**
     * Check if there is any tax.
     */
    public function hasTax(): bool
    {
        return $this->tax > 0;
    }

    /**
     * Check if there is any discount.
     */
    public function hasDiscount(): bool
    {
        return $this->discount > 0;
    }

    /**
     * Check if shipping is free.
     */
    public function isFreeShipping(): bool
    {
        return $this->shipping <= 0;
    }

    /**
     * Check if there are applied coupons.
     */
    public function hasCoupons(): bool
    {
        return !empty($this->appliedCoupons);
    }

    /**
     * Check if cart is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Get total quantity of all items.
     */
    public function getTotalQuantity(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item['quantity'] ?? 0;
        }

        return $total;
    }

    /**
     * Get savings amount (difference between subtotal and total after discounts).
     */
    public function getSavings(): float
    {
        return $this->discount;
    }

    /**
     * Check if checkout can proceed (has required selections).
     */
    public function canCheckout(): bool
    {
        return !$this->isEmpty()
            && $this->deliveryModuleName !== null
            && $this->paymentModuleName !== null;
    }
}
