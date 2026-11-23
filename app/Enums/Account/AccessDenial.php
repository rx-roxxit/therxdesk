<?php

declare(strict_types=1);

namespace App\Enums\Account;

/**
 * Why a post may not publish. Availability (#63) checks a network's switch in
 * the same gate, before the Account's own access.
 */
enum AccessDenial: string
{
    case Lapsed = 'lapsed';
    case Suspended = 'suspended';
    case Unavailable = 'unavailable';
}
