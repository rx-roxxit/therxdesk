<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Models\AvailabilitySwitch;

/**
 * The System admin's Availability switches, read from the database. A missing
 * row counts as on, and the disabled keys are read once per request or job
 * (the container binding is scoped).
 */
class Availability
{
    /**
     * @var list<string>|null
     */
    private ?array $disabledKeys = null;

    public function isOn(string $key): bool
    {
        return ! in_array($key, $this->disabledKeys(), true);
    }

    public function set(string $key, bool $enabled): AvailabilitySwitch
    {
        $switch = AvailabilitySwitch::query()->firstOrNew(['key' => $key]);
        $switch->enabled = $enabled;
        $switch->save();

        $this->disabledKeys = null;

        return $switch;
    }

    /**
     * @return list<string>
     */
    private function disabledKeys(): array
    {
        return $this->disabledKeys ??= AvailabilitySwitch::query()
            ->where('enabled', false)
            ->pluck('key')
            ->all();
    }
}
