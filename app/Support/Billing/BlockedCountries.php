<?php

declare(strict_types=1);

namespace App\Support\Billing;

use Illuminate\Support\Str;

/**
 * The countries The RX Desk does not sell in during v3.0.0 (owner decision
 * November 20, 2026): the EU27, the EEA (Norway, Iceland, Liechtenstein), the
 * UK and the Crown dependencies Jersey, Guernsey and the Isle of Man.
 * Switzerland stays open.
 */
final class BlockedCountries
{
    /**
     * @var list<string>
     */
    public const CODES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        'IS', 'LI', 'NO',
        'GB', 'JE', 'GG', 'IM',
    ];

    public static function isBlocked(?string $country): bool
    {
        if (blank($country)) {
            return false;
        }

        return in_array(Str::upper(trim($country)), self::CODES, true);
    }
}
