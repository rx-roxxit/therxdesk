<?php

declare(strict_types=1);

namespace App\Services\Metrics;

/**
 * Reads the host's memory and load from `/proc`. Docker has no lxcfs here, so
 * these are the host kernel's figures; an unreadable file answers 0 rather
 * than guessing.
 */
class ProcHostMetrics implements HostMetrics
{
    public function usedMemoryPercent(): float
    {
        $raw = @file_get_contents('/proc/meminfo');

        if ($raw === false) {
            return 0.0;
        }

        $values = $this->parse($raw);
        $total = $values['MemTotal'] ?? 0;

        if ($total <= 0) {
            return 0.0;
        }

        if (isset($values['MemAvailable'])) {
            $available = $values['MemAvailable'];
        } else {
            $available = ($values['MemFree'] ?? 0) + ($values['Buffers'] ?? 0) + ($values['Cached'] ?? 0);
        }

        $used = max(0, $total - $available);

        return min(100.0, $used / $total * 100);
    }

    public function loadAverage5(): float
    {
        $raw = @file_get_contents('/proc/loadavg');

        if ($raw === false) {
            return 0.0;
        }

        $parts = explode(' ', trim($raw));

        if (! isset($parts[1]) || ! is_numeric($parts[1])) {
            return 0.0;
        }

        return (float) $parts[1];
    }

    /**
     * @return array<string, int>
     */
    private function parse(string $raw): array
    {
        $values = [];

        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches) !== 1) {
                continue;
            }

            $values[$matches[1]] = (int) $matches[2];
        }

        return $values;
    }
}
