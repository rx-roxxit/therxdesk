<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum Interval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
