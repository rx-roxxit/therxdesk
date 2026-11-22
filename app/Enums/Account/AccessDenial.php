<?php

declare(strict_types=1);

namespace App\Enums\Account;

/**
 * Why an Account may not publish or use the app. Availability (#63) adds its
 * own case and is checked in the same gate.
 */
enum AccessDenial: string
{
    case Lapsed = 'lapsed';
    case Suspended = 'suspended';
}
