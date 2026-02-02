<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\DTO;

/**
 * Data Transfer Object for wishlist items.
 */
final class WishlistItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly ?int $pseId,
        public readonly string $title,
        public readonly string $ref,
        public readonly float $price,
        public readonly ?float $promoPrice,
        public readonly ?string $imageUrl,
        public readonly bool $isPromo,
        public readonly bool $inStock,
        public readonly \DateTimeInterface $addedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            productId: (int) $data['product_id'],
            pseId: isset($data['pse_id']) ? (int) $data['pse_id'] : null,
            title: $data['title'] ?? '',
            ref: $data['ref'] ?? '',
            price: (float) ($data['price'] ?? 0),
            promoPrice: isset($data['promo_price']) ? (float) $data['promo_price'] : null,
            imageUrl: $data['image_url'] ?? null,
            isPromo: (bool) ($data['is_promo'] ?? false),
            inStock: (bool) ($data['in_stock'] ?? true),
            addedAt: new \DateTimeImmutable($data['added_at'] ?? 'now'),
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
            'imageUrl' => $this->imageUrl,
            'isPromo' => $this->isPromo,
            'inStock' => $this->inStock,
            'addedAt' => $this->addedAt->format('c'),
        ];
    }

    public function getEffectivePrice(): float
    {
        return $this->isPromo && $this->promoPrice !== null
            ? $this->promoPrice
            : $this->price;
    }
}
