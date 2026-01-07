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
    public string $type;

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
}
