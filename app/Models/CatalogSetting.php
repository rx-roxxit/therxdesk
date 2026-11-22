<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CatalogSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The Catalog settings the System admin edits: the Trial's length, the tokens
 * one AI credit costs and the AI credits a Trial gets. One row (id 1); a fresh
 * install reads the defaults without writing them.
 */
class CatalogSetting extends Model
{
    /** @use HasFactory<CatalogSettingFactory> */
    use HasFactory;

    public const int SINGLETON_ID = 1;

    /**
     * The values an install starts with.
     *
     * @var array{trial_days: int, tokens_per_credit: int, trial_ai_credits: int}
     */
    public const array DEFAULTS = [
        'trial_days' => 7,
        'tokens_per_credit' => 150,
        'trial_ai_credits' => 1000,
    ];

    /**
     * `id` is fillable so the singleton row is written as row 1 even when the
     * table already holds other rows; `current()` never saves.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'trial_days',
        'tokens_per_credit',
        'trial_ai_credits',
        'stripe_billing_portal_configuration_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
            'tokens_per_credit' => 'integer',
            'trial_ai_credits' => 'integer',
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
