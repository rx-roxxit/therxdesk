<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One Availability switch the System admin flips: a network (`platform:<value>`
 * today, services and new signups later). A missing row means on.
 */
class AvailabilitySwitch extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
