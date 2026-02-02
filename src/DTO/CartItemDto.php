<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\DTO;

use Thelia\Model\CartItem;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;

/**
 * Data Transfer Object for cart items.
 */
final class CartItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly int $pseId,
        public readonly string $title,
        public readonly string $ref,
        public readonly float $price,
        public readonly ?float $promoPrice,
        public readonly int $quantity,
        public readonly int $stock,
        public readonly ?string $imageUrl,
        public readonly bool $isPromo,
        public readonly array $attributes = [],
    ) {}

    public static function fromCartItem(CartItem $cartItem, string $locale = 'fr_FR'): self
    {
        $product = $cartItem->getProduct();
        $pse = $cartItem->getProductSaleElements();

        // Get product title
        $product->setLocale($locale);
        $title = $product->getTitle() ?? '';

        // Get image URL
        $imageUrl = null;
        $image = ProductImageQuery::create()
            ->filterByProductId($product->getId())
            ->filterByPosition(1)
            ->findOne();

        if ($image) {
            $imageUrl = '/cache/images/product/' . $image->getFile();
        }

        // Get PSE attributes
        $attributes = [];
        foreach ($pse->getAttributeCombinations() as $combination) {
            $attribute = $combination->getAttribute();
            $attributeAv = $combination->getAttributeAv();

            $attribute->setLocale($locale);
            $attributeAv->setLocale($locale);

            $attributes[$attribute->getTitle()] = $attributeAv->getTitle();
        }

        return new self(
            id: $cartItem->getId(),
            productId: $product->getId(),
            pseId: $pse->getId(),
            title: $title,
            ref: $pse->getRef() ?? $product->getRef() ?? '',
            price: (float) $cartItem->getPrice(),
            promoPrice: $cartItem->getPromo() ? (float) $cartItem->getPromoPrice() : null,
            quantity: $cartItem->getQuantity(),
            stock: $pse->getQuantity(),
            imageUrl: $imageUrl,
            isPromo: (bool) $cartItem->getPromo(),
            attributes: $attributes,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'pseId' => $this->pseId,
            'title' => $this->title,
            'ref' => $this->ref,
            'price' => $this->price,
            'promoPrice' => $this->promoPrice,
            'quantity' => $this->quantity,
            'stock' => $this->stock,
            'imageUrl' => $this->imageUrl,
            'isPromo' => $this->isPromo,
            'attributes' => $this->attributes,
        ];
    }

    public function getEffectivePrice(): float
    {
        return $this->isPromo && $this->promoPrice !== null
            ? $this->promoPrice
            : $this->price;
    }

    public function getTotalPrice(): float
    {
        return $this->getEffectivePrice() * $this->quantity;
    }
}
