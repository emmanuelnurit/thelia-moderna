<?php

declare(strict_types=1);

namespace Moderna\Tests\Unit\Checkout;

use Moderna\UiComponents\Checkout\CheckoutEvents;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for CheckoutEvents constants.
 */
class CheckoutEventsTest extends TestCase
{
    public function testCartEventsExist(): void
    {
        $this->assertSame('moderna:cart:add-item', CheckoutEvents::ADD_ITEM_EVENT);
        $this->assertSame('moderna:cart:remove-item', CheckoutEvents::REMOVE_ITEM_EVENT);
        $this->assertSame('moderna:cart:update-quantity', CheckoutEvents::UPDATE_QUANTITY_EVENT);
        $this->assertSame('moderna:cart:updated', CheckoutEvents::CART_UPDATED_EVENT);
    }

    public function testPromoCodeEventsExist(): void
    {
        $this->assertSame('moderna:checkout:add-promo-code', CheckoutEvents::ADD_PROMO_CODE);
        $this->assertSame('moderna:checkout:remove-promo-code', CheckoutEvents::REMOVE_PROMO_CODE);
    }

    public function testDeliveryEventsExist(): void
    {
        $this->assertSame('moderna:checkout:set-delivery-address', CheckoutEvents::SET_DELIVERY_ADDRESS_ID);
        $this->assertSame('moderna:checkout:set-delivery-module', CheckoutEvents::SET_DELIVERY_MODULE_ID);
        $this->assertSame('moderna:checkout:set-delivery-option', CheckoutEvents::SET_DELIVERY_MODULE_OPTION);
        $this->assertSame('moderna:checkout:add-new-delivery-address', CheckoutEvents::ADD_NEW_DELIVERY_ADDRESS);
    }

    public function testInvoiceEventsExist(): void
    {
        $this->assertSame('moderna:checkout:set-invoice-address', CheckoutEvents::SET_INVOICE_ADDRESS_ID);
        $this->assertSame('moderna:checkout:add-new-invoice-address', CheckoutEvents::ADD_NEW_INVOICE_ADDRESS);
        $this->assertSame('moderna:checkout:use-delivery-as-invoice', CheckoutEvents::USE_DELIVERY_AS_INVOICE);
    }

    public function testPaymentEventsExist(): void
    {
        $this->assertSame('moderna:checkout:set-payment-module', CheckoutEvents::SET_PAYMENT_MODULE_ID);
    }

    public function testSummaryEventsExist(): void
    {
        $this->assertSame('moderna:checkout:sync-summary', CheckoutEvents::SYNC_SUMMARY);
    }

    public function testDrawerEventsExist(): void
    {
        $this->assertSame('moderna:drawer:open-cart', CheckoutEvents::OPEN_CART_DRAWER);
        $this->assertSame('moderna:drawer:close-cart', CheckoutEvents::CLOSE_CART_DRAWER);
    }

    public function testAllConstantsHaveModernaPrefix(): void
    {
        $reflection = new ReflectionClass(CheckoutEvents::class);
        $constants = $reflection->getConstants();

        foreach ($constants as $name => $value) {
            $this->assertStringStartsWith(
                'moderna:',
                $value,
                sprintf('Constant %s should have "moderna:" prefix, got "%s"', $name, $value)
            );
        }
    }

    public function testConstantsAreUnique(): void
    {
        $reflection = new ReflectionClass(CheckoutEvents::class);
        $constants = $reflection->getConstants();

        $values = array_values($constants);
        $uniqueValues = array_unique($values);

        $this->assertCount(
            count($values),
            $uniqueValues,
            'All event constants should have unique values'
        );
    }

    public function testClassIsFinal(): void
    {
        $reflection = new ReflectionClass(CheckoutEvents::class);

        $this->assertTrue($reflection->isFinal(), 'CheckoutEvents class should be final');
    }

    public function testNoPublicMethods(): void
    {
        $reflection = new ReflectionClass(CheckoutEvents::class);
        $publicMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $this->assertCount(
            0,
            $publicMethods,
            'CheckoutEvents should not have any public methods (constants only)'
        );
    }
}
