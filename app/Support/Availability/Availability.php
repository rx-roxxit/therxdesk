<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Models\AvailabilitySwitch;

/**
 * The System admin's Availability switches, read from the database once per
 * request or job (the container binding is scoped). A missing row counts as
 * on, except for the services that start off.
 */
class Availability
{
    /**
     * @var array<string, bool>|null
     */
    private ?array $states = null;

    /**
     * `$default` decides a key with no row yet. It is true for networks and
     * most services, and false for the two the owner decided start off
     * (Support chat and new signups), so they stay closed until the System
     * admin switches them on.
     */
    public function isOn(string $key, bool $default = true): bool
    {
        return $this->states()[$key] ?? $default;
    }

    public function set(string $key, bool $enabled): AvailabilitySwitch
    {
        $switch = AvailabilitySwitch::query()->firstOrNew(['key' => $key]);
        $switch->enabled = $enabled;
        $switch->save();

        $this->states = null;

        return $switch;
    }

    /**
     * @return array<string, bool>
     */
    private function states(): array
    {
        return $this->states ??= AvailabilitySwitch::query()
            ->pluck('enabled', 'key')
            ->map(fn (mixed $enabled): bool => (bool) $enabled)
            ->all();
    }
}
