<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * What claiming a Trial slot did: this subscription claimed it, the slot is
 * already this subscription's own (a repeated delivery of the same event), or
 * another subscription holds it.
 */
enum TrialClaim
{
    case Claimed;
    case Owned;
    case Taken;
}
