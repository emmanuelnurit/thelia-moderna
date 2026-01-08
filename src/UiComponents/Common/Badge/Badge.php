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

    /**
     * Resolves the badge text based on type and custom text.
     *
     * Priority: custom text > type-based generation.
     * For 'promo' type, formats the value as a percentage (e.g., "-25%").
     * Returns empty string for 'status' type and unknown types.
     *
     * @return string Badge display text
     */
    public function getText(): string
    {
        // If custom text provided, use it
        if ($this->text !== null) {
            return $this->text;
        }

        // Generate text based on type
        return match ($this->type) {
            'new' => 'New',
            'promo' => $this->value !== null ? "-{$this->value}%" : '',
            'default' => 'Default',
            'status' => '',
            default => '',
        };
    }

    /**
     * Generates the CSS class string for the badge element.
     *
     * Combines the base "badge" class with a color-specific modifier class.
     * Example output: "badge badge--green" or "badge badge--red".
     *
     * @return string Space-separated CSS class names
     */
    public function getCssClass(): string
    {
        return "badge badge--{$this->getColor()}";
    }

    /**
     * Determines whether the badge should display an icon (status dot).
     *
     * Icons are only shown for 'status' type badges to visually indicate
     * order or item status (e.g., paid, delivered, canceled).
     *
     * @return bool True if badge should display an icon, false otherwise
     */
    public function hasIcon(): bool
    {
        // Icon (dot) only for status badges
        return $this->type === 'status';
    }
}
