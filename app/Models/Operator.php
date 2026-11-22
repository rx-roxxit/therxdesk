<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OperatorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The legal entity that sells The RX Desk. Exactly one row is current
 * (`ended_at` is null); every earlier row keeps the dates it was current for.
 * The cached lookup is cleared by anything that writes through
 * `App\Services\Operator\OperatorCatalog`.
 */
class Operator extends Model
{
    /** @use HasFactory<OperatorFactory> */
    use HasFactory, HasUuids;

    public const string CURRENT_CACHE_KEY = 'rxdesk:operator:current';

    protected $fillable = [
        'legal_name_th',
        'legal_name_latin',
        'address_th',
        'address_latin',
        'tax_id',
        'vat_id',
        'contact_email',
        'phone',
        'effective_from',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * The one current row, cached under a wrapper array so a null result is
     * cached too (`Cache::rememberForever` treats a cached null as a miss).
     *
     * The cache holds the row's attributes, never the model: cache stores
     * serialize their values and `cache.serializable_classes` is false, so a
     * stored model would come back as `__PHP_Incomplete_Class`. `hydrate()`
     * rebuilds the same read-only model from the attributes.
     */
    public static function current(): ?self
    {
        $cached = Cache::rememberForever(self::CURRENT_CACHE_KEY, fn (): array => [
            'current' => self::query()->current()->latest('effective_from')->first()?->toArray(),
        ]);

        $attributes = data_get($cached, 'current');

        if (blank($attributes)) {
            return null;
        }

        return self::hydrate([$attributes])->first();
    }

    public static function forgetCurrent(): void
    {
        Cache::forget(self::CURRENT_CACHE_KEY);
    }

    /**
     * The rows that already ended, newest first.
     *
     * @return Collection<int, self>
     */
    public static function history(): Collection
    {
        return self::query()
            ->whereNotNull('ended_at')
            ->orderByDesc('effective_from')
            ->get();
    }
}
