<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\NetworkCallBucketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * How many of the app's own calls one network has taken today (UTC). The
 * capacity is configuration, not a column, and `exhausted_at` records the
 * moment the bucket ran out so publishing can wait for the reset.
 */
class NetworkCallBucket extends Model
{
    /** @use HasFactory<NetworkCallBucketFactory> */
    use HasFactory;

    protected $fillable = [
        'network',
        'date',
        'calls',
        'exhausted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'calls' => 'integer',
            'exhausted_at' => 'datetime',
        ];
    }

    /**
     * Count one call and return the new total. The upsert is the one raw
     * expression this feature keeps: it increments under the unique index, so
     * two workers cannot lose a call to a read-then-write race.
     */
    public static function incrementFor(string $network, CarbonImmutable $date): int
    {
        $day = $date->toDateString();
        $now = now();

        static::query()->upsert(
            [[
                'network' => $network,
                'date' => $day,
                'calls' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['network', 'date'],
            [
                'calls' => DB::raw('network_call_buckets.calls + 1'),
                'updated_at' => $now,
            ],
        );

        return (int) static::query()
            ->where('network', $network)
            ->where('date', $day)
            ->value('calls');
    }
}
