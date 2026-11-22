<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * How an Offer reaches an Owner: every Owner checking out in its window
 * (general), or only the Owner who opened its `?offer=` link (link).
 */
enum OfferReach: string
{
    case General = 'general';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Link => 'Link',
        };
    }
}
