<?php

declare(strict_types=1);

namespace App\Enums\Alerts;

/**
 * How an admin alert is delivered: at once, when it clears, at most once per
 * few hours, or in the daily digest.
 */
enum AlertLevel: string
{
    case Urgent = 'urgent';
    case Resolved = 'resolved';
    case Today = 'today';
    case Digest = 'digest';

    /**
     * The level in the email subject, such as `[URGENT]`.
     */
    public function label(): string
    {
        return match ($this) {
            self::Urgent => 'URGENT',
            self::Resolved => 'RESOLVED',
            self::Today => 'TODAY',
            self::Digest => 'DIGEST',
        };
    }
}
