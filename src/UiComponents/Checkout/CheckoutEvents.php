<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Checkout;

/**
 * Events emitted by checkout Live Components for cross-component communication.
 */
final class CheckoutEvents
{
    // Cart events
    public const ADD_ITEM_EVENT = 'moderna:cart:add-item';
    public const REMOVE_ITEM_EVENT = 'moderna:cart:remove-item';
    public const UPDATE_QUANTITY_EVENT = 'moderna:cart:update-quantity';
    public const CART_UPDATED_EVENT = 'moderna:cart:updated';

    // Promo code events
    public const ADD_PROMO_CODE = 'moderna:checkout:add-promo-code';
    public const REMOVE_PROMO_CODE = 'moderna:checkout:remove-promo-code';

    // Delivery events
    public const SET_DELIVERY_ADDRESS_ID = 'moderna:checkout:set-delivery-address';
    public const SET_DELIVERY_MODULE_ID = 'moderna:checkout:set-delivery-module';
    public const SET_DELIVERY_MODULE_OPTION = 'moderna:checkout:set-delivery-option';
    public const ADD_NEW_DELIVERY_ADDRESS = 'moderna:checkout:add-new-delivery-address';

    // Invoice events
    public const SET_INVOICE_ADDRESS_ID = 'moderna:checkout:set-invoice-address';
    public const ADD_NEW_INVOICE_ADDRESS = 'moderna:checkout:add-new-invoice-address';
    public const USE_DELIVERY_AS_INVOICE = 'moderna:checkout:use-delivery-as-invoice';

    // Payment events
    public const SET_PAYMENT_MODULE_ID = 'moderna:checkout:set-payment-module';

    // Summary events
    public const SYNC_SUMMARY = 'moderna:checkout:sync-summary';

    // Drawer events
    public const OPEN_CART_DRAWER = 'moderna:drawer:open-cart';
    public const CLOSE_CART_DRAWER = 'moderna:drawer:close-cart';
}
