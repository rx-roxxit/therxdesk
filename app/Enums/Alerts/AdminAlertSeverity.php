<?php

declare(strict_types=1);

namespace App\Enums\Alerts;

/**
 * How soon an alert condition must reach the System admin.
 */
enum AdminAlertSeverity: string
{
    case Urgent = 'urgent';
    case Today = 'today';
}
