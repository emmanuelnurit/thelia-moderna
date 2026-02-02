<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Cart;

use Moderna\DTO\CartItemDto;
use Moderna\UiComponents\Checkout\CheckoutEvents;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\HttpFoundation\Session\Session;

/**
 * LiveComponent for the cart drawer (slide-out panel).
 *
 * This component handles the mini-cart display:
 * - Slide-out drawer with cart items
 * - Quick view of cart contents
 * - Link to full cart/checkout
 * - Auto-open when items are added
 *
 * Usage in Twig:
 * {{ component('Moderna:Cart:Drawer') }}
 */
#[AsLiveComponent(name: 'Moderna:Cart:Drawer', template: '@UiComponents/Cart/CartDrawer.html.twig')]
class CartDrawer
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * Whether the drawer is currently open.
     */
    #[LiveProp]
    public bool $isOpen = false;

    /**
     * Cart items as arrays (serializable for LiveProp).
     *
     * @var array<int, array>
     */
    #[LiveProp]
    public array $items = [];

    /**
     * Cart subtotal before discounts.
     */
    #[LiveProp]
    public float $subtotal = 0;

    /**
     * Total number of items in cart.
     */
    #[LiveProp]
    public int $itemCount = 0;

    public function __construct(
        private readonly Session $session,
    ) {
    }

    /**
     * Initialize component by loading cart items.
     */
    public function mount(): void
    {
        $this->loadCartItems();
    }

    /**
     * Load cart items from session and calculate totals.
     */
    private function loadCartItems(): void
    {
        $cart = $this->session->getSessionCart();
        if (!$cart) {
            $this->items = [];
            $this->subtotal = 0;
            $this->itemCount = 0;

            return;
        }

        $this->items = [];
        $this->subtotal = 0;
        $locale = $this->session->getLang()->getLocale();

        foreach ($cart->getCartItems() as $cartItem) {
            $dto = CartItemDto::fromCartItem($cartItem, $locale);
            $this->items[] = $dto->toArray();
            $this->subtotal += $dto->getTotalPrice();
        }

        $this->itemCount = count($this->items);
    }

    /**
     * Open the cart drawer.
     */
    #[LiveAction]
    public function open(): void
    {
        $this->isOpen = true;
        $this->loadCartItems();
    }

    /**
     * Close the cart drawer.
     */
    #[LiveAction]
    public function close(): void
    {
        $this->isOpen = false;
    }

    /**
     * Toggle the cart drawer state.
     */
    #[LiveAction]
    public function toggle(): void
    {
        $this->isOpen = !$this->isOpen;
        if ($this->isOpen) {
            $this->loadCartItems();
        }
    }

    /**
     * Respond to item added event - open drawer and refresh.
     */
    #[LiveListener(CheckoutEvents::ADD_ITEM_EVENT)]
    public function onItemAdded(): void
    {
        $this->loadCartItems();
        $this->isOpen = true;
    }

    /**
     * Respond to cart updated event - refresh items.
     */
    #[LiveListener(CheckoutEvents::CART_UPDATED_EVENT)]
    public function onCartUpdated(): void
    {
        $this->loadCartItems();
    }

    /**
     * Respond to open drawer event.
     */
    #[LiveListener(CheckoutEvents::OPEN_CART_DRAWER)]
    public function onOpenDrawer(): void
    {
        $this->open();
    }

    /**
     * Respond to close drawer event.
     */
    #[LiveListener(CheckoutEvents::CLOSE_CART_DRAWER)]
    public function onCloseDrawer(): void
    {
        $this->close();
    }

    /**
     * Check if the cart is empty.
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
            $total += $item['quantity'];
        }

        return $total;
    }
}
