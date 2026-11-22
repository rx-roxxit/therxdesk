<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum Interval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function stripeInterval(): string
    {
        return match ($this) {
            self::Monthly => 'month',
            self::Yearly => 'year',
        };
    }
}
