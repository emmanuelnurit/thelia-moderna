<?php

declare(strict_types=1);

namespace ModernaBundle\UiComponents\Common\Badge;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent(name: 'Moderna:Badge')]
class Badge
{
    #[ExposeInTemplate]
    public string $type;

    #[ExposeInTemplate]
    public ?string $text = null;

    #[ExposeInTemplate]
    public ?int $value = null;

    #[ExposeInTemplate]
    public ?string $statusCode = null;

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

    private const TYPE_COLOR_MAP = [
        'new' => 'green',
        'promo' => 'red',
        'default' => 'gray',
    ];
}
