<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Cart;

use Moderna\DTO\CartItemDto;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * TwigComponent for displaying a single cart item.
 *
 * Usage in Twig:
 * {{ component('Moderna:Cart:Item', { item: cartItemDto }) }}
 */
#[AsTwigComponent(name: 'Moderna:Cart:Item', template: '@templates/frontOffice/moderna/components/UiComponents/Cart/CartItem.html.twig')]
class CartItem
{
    /**
     * Cart item data transfer object.
     */
    public CartItemDto $item;

    /**
     * Whether to show the remove button.
     */
    public bool $showRemove = true;

    /**
     * Compact display mode for cart drawer.
     */
    public bool $compact = false;
}
