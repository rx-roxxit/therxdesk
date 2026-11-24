<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AccountNetworkCallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One Account's share of a network's calls on a UTC day, for the digest that
 * names the Accounts using a shared bucket.
 */
class AccountNetworkCall extends Model
{
    /** @use HasFactory<AccountNetworkCallFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'network',
        'date',
        'calls',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'calls' => 'integer',
        ];
    }

    /**
     * Count one call for the Account under the unique index, so a concurrent
     * call from another worker is never lost.
     */
    public static function incrementFor(string $accountId, string $network, CarbonImmutable $date): void
    {
        $day = $date->toDateString();
        $now = now();

        static::query()->upsert(
            [[
                'account_id' => $accountId,
                'network' => $network,
                'date' => $day,
                'calls' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['account_id', 'network', 'date'],
            [
                'calls' => DB::raw('account_network_calls.calls + 1'),
                'updated_at' => $now,
            ],
        );
    }
}
