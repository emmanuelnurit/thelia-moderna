<?php

declare(strict_types=1);

namespace ModernaBundle\UiComponents\Common\Badge;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Badge component for displaying content badges (new, promo, default, status).
 * Provides centralized color mapping for order statuses and product types.
 */
#[AsTwigComponent(name: 'Moderna:Badge')]
class Badge
{
    /** Type de badge: 'new', 'promo', 'default', 'status' */
    #[ExposeInTemplate]
    public string $type = 'default';

    /** Texte custom optionnel pour le badge */
    #[ExposeInTemplate]
    public ?string $text = null;

    /** Valeur numérique pour type='promo' (ex: 25 pour -25%) */
    #[ExposeInTemplate]
    public ?int $value = null;

    /** Code status pour type='status' (paid, delivered, etc.) */
    #[ExposeInTemplate]
    public ?string $statusCode = null;

    /** @var array<string, string> Maps order status codes to color variants */
    private const STATUS_COLOR_MAP = [
        'paid' => 'blue',
        'processing' => 'blue',
        'sent' => 'green',
        'delivered' => 'green',
        'canceled' => 'red',
        'refunded' => 'red',
        'pending' => 'amber',
        'not_paid' => 'amber',
    ];

    /** @var array<string, string> Maps badge types to color variants */
    private const TYPE_COLOR_MAP = [
        'new' => 'green',
        'promo' => 'red',
        'default' => 'gray',
    ];

    /**
     * Resolves the color variant based on badge type and status code.
     *
     * For 'status' type badges, maps the statusCode to a color variant.
     * For other types ('new', 'promo', 'default'), uses the type mapping.
     * Falls back to 'gray' for unknown types or status codes.
     *
     * @return string Color variant name (e.g., 'green', 'blue', 'red', 'amber', 'gray')
     */
    public function getColor(): string
    {
        // For status type, use status code mapping
        if ($this->type === 'status' && $this->statusCode !== null) {
            return self::STATUS_COLOR_MAP[$this->statusCode] ?? 'gray';
        }

        // For other types, use type mapping
        return self::TYPE_COLOR_MAP[$this->type] ?? 'gray';
    }

    public function getText(): string
    {
        // If custom text provided, use it
        if ($this->text !== null) {
            return $this->text;
        }

        // Generate text based on type
        return match($this->type) {
            'new' => 'New',
            'promo' => $this->value !== null ? "-{$this->value}%" : '',
            'default' => 'Default',
            'status' => '',
            default => '',
        };
    }
}
