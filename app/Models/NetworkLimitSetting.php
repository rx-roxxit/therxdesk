<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NetworkLimitSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The System admin's network bucket thresholds. One row (id 1); a fresh
 * install reads the defaults without writing them.
 */
class NetworkLimitSetting extends Model
{
    /** @use HasFactory<NetworkLimitSettingFactory> */
    use HasFactory;

    public const int SINGLETON_ID = 1;

    /**
     * @var array{today_percent: int, urgent_percent: int, account_digest_percent: int}
     */
    public const array DEFAULTS = [
        'today_percent' => 70,
        'urgent_percent' => 90,
        'account_digest_percent' => 10,
    ];

    /**
     * `id` is fillable so the singleton row is written as row 1; `current()`
     * never saves.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'today_percent',
        'urgent_percent',
        'account_digest_percent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'today_percent' => 'integer',
            'urgent_percent' => 'integer',
            'account_digest_percent' => 'integer',
        ];
    }

    /**
     * The settings row, or an unsaved model carrying the defaults: a read
     * never writes.
     */
    public static function current(): self
    {
        $setting = static::query()->find(self::SINGLETON_ID);

        if ($setting !== null) {
            return $setting;
        }

        $setting = new self(self::DEFAULTS);
        $setting->setAttribute($setting->getKeyName(), self::SINGLETON_ID);

        return $setting;
    }
}
