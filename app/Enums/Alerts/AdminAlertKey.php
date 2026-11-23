<?php

declare(strict_types=1);

namespace App\Enums\Alerts;

/**
 * Every condition the admin alert system watches. One `admin_alerts` row per
 * key is the open/closed state; the severity and the title come from the key.
 */
enum AdminAlertKey: string
{
    case CapacityRam = 'capacity_ram';
    case CapacityLoad = 'capacity_load';
    case CapacityWait = 'capacity_wait';
    case CapacityDiskFree = 'capacity_disk_free';
    case DiskGuard = 'disk_guard';
    case AiProviderFailure = 'ai_provider_failure';
    case NightwatchBurst = 'nightwatch_burst';

    public function severity(): AdminAlertSeverity
    {
        return match ($this) {
            self::CapacityRam,
            self::CapacityLoad,
            self::CapacityWait,
            self::CapacityDiskFree,
            self::NightwatchBurst => AdminAlertSeverity::Today,
            self::DiskGuard,
            self::AiProviderFailure => AdminAlertSeverity::Urgent,
        };
    }

    /**
     * The admin-facing title of the condition, translated in the current locale.
     */
    public function title(): string
    {
        return __("rxdesk.alerts.conditions.{$this->value}");
    }
}
