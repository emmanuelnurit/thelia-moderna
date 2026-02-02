<?php

declare(strict_types=1);

namespace Moderna\Tests\Unit\DTO;

use Moderna\DTO\CartItemDto;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CartItemDto.
 */
class CartItemDtoTest extends TestCase
{
    public function testConstructor(): void
    {
        $dto = new CartItemDto(
            id: 1,
            productId: 10,
            pseId: 100,
            title: 'Test Product',
            ref: 'REF-001',
            price: 29.99,
            promoPrice: null,
            quantity: 2,
            stock: 50,
            imageUrl: '/cache/images/product/test.jpg',
            isPromo: false,
            attributes: ['Color' => 'Red', 'Size' => 'L'],
        );

        $this->assertSame(1, $dto->id);
        $this->assertSame(10, $dto->productId);
        $this->assertSame(100, $dto->pseId);
        $this->assertSame('Test Product', $dto->title);
        $this->assertSame('REF-001', $dto->ref);
        $this->assertSame(29.99, $dto->price);
        $this->assertNull($dto->promoPrice);
        $this->assertSame(2, $dto->quantity);
        $this->assertSame(50, $dto->stock);
        $this->assertSame('/cache/images/product/test.jpg', $dto->imageUrl);
        $this->assertFalse($dto->isPromo);
        $this->assertSame(['Color' => 'Red', 'Size' => 'L'], $dto->attributes);
    }

    public function testToArray(): void
    {
        $dto = new CartItemDto(
            id: 1,
            productId: 10,
            pseId: 100,
            title: 'Test Product',
            ref: 'REF-001',
            price: 29.99,
            promoPrice: 24.99,
            quantity: 2,
            stock: 50,
            imageUrl: '/cache/images/product/test.jpg',
            isPromo: true,
            attributes: ['Color' => 'Red'],
        );

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertSame(1, $array['id']);
        $this->assertSame(10, $array['productId']);
        $this->assertSame(100, $array['pseId']);
        $this->assertSame('Test Product', $array['title']);
        $this->assertSame('REF-001', $array['ref']);
        $this->assertSame(29.99, $array['price']);
        $this->assertSame(24.99, $array['promoPrice']);
        $this->assertSame(2, $array['quantity']);
        $this->assertSame(50, $array['stock']);
        $this->assertSame('/cache/images/product/test.jpg', $array['imageUrl']);
        $this->assertTrue($array['isPromo']);
        $this->assertSame(['Color' => 'Red'], $array['attributes']);
    }

    public function testGetEffectivePriceWithoutPromo(): void
    {
        $dto = new CartItemDto(
            id: 1,
            productId: 10,
            pseId: 100,
            title: 'Test Product',
            ref: 'REF-001',
            price: 29.99,
            promoPrice: null,
            quantity: 1,
            stock: 50,
            imageUrl: null,
            isPromo: false,
        );

        $this->assertSame(29.99, $dto->getEffectivePrice());
    }

    public function testGetEffectivePriceWithPromo(): void
    {
        $dto = new CartItemDto(
            id: 1,
            productId: 10,
            pseId: 100,
            title: 'Test Product',
            ref: 'REF-001',
            price: 29.99,
            promoPrice: 24.99,
            quantity: 1,
            stock: 50,
            imageUrl: null,
            isPromo: true,
        );

        $this->assertSame(24.99, $dto->getEffectivePrice());
    }

    public function testGetEffectivePricePromoFlagFalseButPriceSet(): void
    {
        $dto = new CartItemDto(
            id: 1,
            productId: 10,
            pseId: 100,
            title: 'Test Product',
            ref: 'REF-001',
            price: 29.99,
            promoPrice: 24.99,
            quantity: 1,
            stock: 50,
            imageUrl: null,
            isPromo: false,
        );

        // Should return regular price when isPromo is false
        $this->assertSame(29.99, $dto->getEffectivePrice());
    }

    public function testGetTotalPrice(): void
    {
        $dto = new CartItemDto(
            id: 1,
            productId: 10,
            pseId: 100,
            title: 'Test Product',
            ref: 'REF-001',
            price: 29.99,
            promoPrice: null,
            quantity: 3,
            stock: 50,
            imageUrl: null,
            isPromo: false,
        );

        $this->assertEqualsWithDelta(89.97, $dto->getTotalPrice(), 0.001);
    }

    public function testGetTotalPriceWithPromo(): void
    {
        $dto = new CartItemDto(
            id: 1,
            productId: 10,
            pseId: 100,
            title: 'Test Product',
            ref: 'REF-001',
            price: 29.99,
            promoPrice: 20.00,
            quantity: 3,
            stock: 50,
            imageUrl: null,
            isPromo: true,
        );

        $this->assertEqualsWithDelta(60.00, $dto->getTotalPrice(), 0.001);
    }

    public function testImmutability(): void
    {
        $dto = new CartItemDto(
            id: 1,
            productId: 10,
            pseId: 100,
            title: 'Test Product',
            ref: 'REF-001',
            price: 29.99,
            promoPrice: null,
            quantity: 1,
            stock: 50,
            imageUrl: null,
            isPromo: false,
        );

        // Properties are readonly, attempting to modify should fail
        $this->expectException(\Error::class);
        $dto->price = 100.00;
    }

    public function testDefaultAttributes(): void
    {
        $dto = new CartItemDto(
            id: 1,
            productId: 10,
            pseId: 100,
            title: 'Test Product',
            ref: 'REF-001',
            price: 29.99,
            promoPrice: null,
            quantity: 1,
            stock: 50,
            imageUrl: null,
            isPromo: false,
        );

        $this->assertSame([], $dto->attributes);
    }
}
