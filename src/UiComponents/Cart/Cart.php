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
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\CartItem as CartItemModel;
use Thelia\Model\CartItemQuery;

/**
 * LiveComponent for the shopping cart.
 *
 * This component handles cart display and manipulation:
 * - Display cart items with quantities and prices
 * - Update item quantities (plus/minus/direct input)
 * - Remove items with undo capability
 * - Respond to promo code events
 *
 * Usage in Twig:
 * {{ component('Moderna:Cart') }}
 */
#[AsLiveComponent(name: 'Moderna:Cart', template: '@templates/frontOffice/moderna/components/UiComponents/Cart/Cart.html.twig')]
class Cart
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * Cart items as arrays (serializable for LiveProp).
     *
     * @var array<int, array>
     */
    #[LiveProp]
    public array $items = [];

    /**
     * Pending delete item data for undo functionality.
     */
    #[LiveProp]
    public ?array $pendingDelete = null;

    /**
     * Cart subtotal before discounts.
     */
    #[LiveProp]
    public float $subtotal = 0;

    /**
     * Discount amount applied to the cart.
     */
    #[LiveProp]
    public float $discount = 0;

    /**
     * Cart total after discounts.
     */
    #[LiveProp]
    public float $total = 0;

    /**
     * Flag indicating if any item has stock issues.
     */
    public bool $hasStockWarning = false;

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
            $this->total = 0;
            $this->hasStockWarning = false;

            return;
        }

        $this->items = [];
        $this->subtotal = 0;
        $this->hasStockWarning = false;

        foreach ($cart->getCartItems() as $cartItem) {
            $dto = CartItemDto::fromCartItem($cartItem, $this->session->getLang()->getLocale());
            $this->items[] = $dto->toArray();
            $this->subtotal += $dto->getTotalPrice();

            // Check stock availability
            if ($dto->stock <= 0 || $dto->stock < $dto->quantity) {
                $this->hasStockWarning = true;
            }
        }

        $this->discount = (float) $cart->getDiscount();
        $this->total = $this->subtotal - $this->discount;
    }

    /**
     * Update the quantity of a cart item.
     */
    #[LiveAction]
    public function updateQuantity(#[LiveArg] int $itemId, #[LiveArg] int $quantity): void
    {
        $cartItem = CartItemQuery::create()->findPk($itemId);
        if ($cartItem && $quantity > 0) {
            $stock = $cartItem->getProductSaleElements()->getQuantity();
            $cartItem->setQuantity(min($quantity, $stock));
            $cartItem->save();
        }
        $this->loadCartItems();
        $this->emit(CheckoutEvents::CART_UPDATED_EVENT);
    }

    /**
     * Decrease quantity by one.
     */
    #[LiveAction]
    public function minus(#[LiveArg] int $itemId): void
    {
        $cartItem = CartItemQuery::create()->findPk($itemId);
        if ($cartItem && $cartItem->getQuantity() > 1) {
            $cartItem->setQuantity($cartItem->getQuantity() - 1);
            $cartItem->save();
        }
        $this->loadCartItems();
        $this->emit(CheckoutEvents::CART_UPDATED_EVENT);
    }

    /**
     * Increase quantity by one (respecting stock limits).
     */
    #[LiveAction]
    public function plus(#[LiveArg] int $itemId): void
    {
        $cartItem = CartItemQuery::create()->findPk($itemId);
        if ($cartItem) {
            $stock = $cartItem->getProductSaleElements()->getQuantity();
            if ($cartItem->getQuantity() < $stock) {
                $cartItem->setQuantity($cartItem->getQuantity() + 1);
                $cartItem->save();
            }
        }
        $this->loadCartItems();
        $this->emit(CheckoutEvents::CART_UPDATED_EVENT);
    }

    /**
     * Remove an item from the cart (with undo capability).
     */
    #[LiveAction]
    public function remove(#[LiveArg] int $itemId): void
    {
        $cartItem = CartItemQuery::create()->findPk($itemId);
        if ($cartItem) {
            // Store item data for potential restore
            $this->pendingDelete = CartItemDto::fromCartItem(
                $cartItem,
                $this->session->getLang()->getLocale()
            )->toArray();
            $cartItem->delete();
        }
        $this->loadCartItems();
        $this->emit(CheckoutEvents::CART_UPDATED_EVENT);
    }

    /**
     * Restore a deleted cart item.
     */
    #[LiveAction]
    public function restoreCartItem(): void
    {
        if (!$this->pendingDelete) {
            return;
        }

        $cart = $this->session->getSessionCart();
        if (!$cart) {
            return;
        }

        $newItem = new CartItemModel();
        $newItem->setCartId($cart->getId())
            ->setProductId($this->pendingDelete['productId'])
            ->setProductSaleElementsId($this->pendingDelete['pseId'])
            ->setQuantity($this->pendingDelete['quantity'])
            ->setPrice($this->pendingDelete['price'])
            ->setPromoPrice($this->pendingDelete['promoPrice'] ?? 0)
            ->setPromo($this->pendingDelete['isPromo'] ? 1 : 0);
        $newItem->save();

        $this->pendingDelete = null;
        $this->loadCartItems();
        $this->emit(CheckoutEvents::CART_UPDATED_EVENT);
    }

    /**
     * Clear the pending delete state.
     */
    #[LiveAction]
    public function clearPendingDelete(): void
    {
        $this->pendingDelete = null;
    }

    /**
     * Respond to promo code addition.
     */
    #[LiveListener(CheckoutEvents::ADD_PROMO_CODE)]
    public function onPromoCodeAdded(): void
    {
        $this->loadCartItems();
    }

    /**
     * Respond to promo code removal.
     */
    #[LiveListener(CheckoutEvents::REMOVE_PROMO_CODE)]
    public function onPromoCodeRemoved(): void
    {
        $this->loadCartItems();
    }

    /**
     * Get the total number of items in the cart.
     */
    public function getItemCount(): int
    {
        return count($this->items);
    }

    /**
     * Get the total quantity of all items.
     */
    public function getTotalQuantity(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item['quantity'];
        }

        return $total;
    }

    /**
     * Check if the cart is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }
}
