<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Toast;

use Moderna\UiComponents\Checkout\CheckoutEvents;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * LiveComponent for toast notifications when adding items to cart.
 *
 * This component handles:
 * - Showing success/error notifications
 * - Displaying added product info
 * - Auto-dismiss functionality
 * - Multiple notification types
 *
 * Usage in Twig:
 * {{ component('Moderna:Toast:AddToCart') }}
 */
#[AsLiveComponent(name: 'Moderna:Toast:AddToCart', template: '@UiComponents/Toast/AddToCartToast.html.twig')]
class AddToCartToast
{
    use DefaultActionTrait;

    /**
     * Whether the toast is currently visible.
     */
    #[LiveProp]
    public bool $visible = false;

    /**
     * The product title to display.
     */
    #[LiveProp]
    public ?string $productTitle = null;

    /**
     * The product image URL to display.
     */
    #[LiveProp]
    public ?string $productImage = null;

    /**
     * The quantity that was added.
     */
    #[LiveProp]
    public int $quantity = 1;

    /**
     * The notification type: 'success', 'error', 'info', 'warning'.
     */
    #[LiveProp]
    public string $type = 'success';

    /**
     * Optional custom message to display.
     */
    #[LiveProp]
    public ?string $message = null;

    /**
     * Respond to item added event - show toast with product info.
     */
    #[LiveListener(CheckoutEvents::ADD_ITEM_EVENT)]
    public function onItemAdded(
        #[LiveArg] ?string $productTitle = null,
        #[LiveArg] ?string $productImage = null,
        #[LiveArg] int $quantity = 1
    ): void {
        $this->productTitle = $productTitle;
        $this->productImage = $productImage;
        $this->quantity = $quantity;
        $this->type = 'success';
        $this->message = null;
        $this->visible = true;
    }

    /**
     * Dismiss the toast notification.
     */
    #[LiveAction]
    public function dismiss(): void
    {
        $this->visible = false;
    }

    /**
     * Show the toast with custom type and message.
     */
    #[LiveAction]
    public function show(
        #[LiveArg] string $type = 'success',
        #[LiveArg] ?string $message = null
    ): void {
        $this->type = $type;
        $this->message = $message;
        $this->visible = true;
    }

    /**
     * Check if this is a success notification.
     */
    public function isSuccess(): bool
    {
        return $this->type === 'success';
    }

    /**
     * Check if this is an error notification.
     */
    public function isError(): bool
    {
        return $this->type === 'error';
    }

    /**
     * Check if this is an info notification.
     */
    public function isInfo(): bool
    {
        return $this->type === 'info';
    }

    /**
     * Check if this is a warning notification.
     */
    public function isWarning(): bool
    {
        return $this->type === 'warning';
    }
}
