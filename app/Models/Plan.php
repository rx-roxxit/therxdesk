<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\Interval;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'quota_bytes',
        'ai_credits_per_month',
        'workspace_limit',
        'sort',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'workspace_limit' => 'integer',
            'quota_bytes' => 'integer',
            'ai_credits_per_month' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * The active price of an interval, the most recently added one when a
     * plan changed its price without deleting the old row.
     */
    public function priceFor(Interval $interval): ?PlanPrice
    {
        if ($this->relationLoaded('prices')) {
            return $this->prices
                ->sortByDesc('created_at')
                ->first(fn (PlanPrice $price): bool => $price->interval === $interval && $price->is_active);
        }

        return $this->prices()
            ->where('interval', $interval)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    /**
     * The interval of a Stripe price, including one this plan no longer sells:
     * a subscription keeps billing on the price it was started on.
     */
    public function intervalFor(?string $priceId): ?Interval
    {
        if (blank($priceId)) {
            return null;
        }

        if ($this->relationLoaded('prices')) {
            return $this->prices->firstWhere('stripe_price_id', $priceId)?->interval;
        }

        return $this->prices()->where('stripe_price_id', $priceId)->first()?->interval;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /**
     * The plan the Welcome plan step offers: the first active plan by sort.
     */
    public static function entryPlan(): ?Plan
    {
        return static::query()
            ->active()
            ->orderBy('sort')
            ->orderBy('name')
            ->first();
    }

    /**
     * @param  array<int, string>  $priceIds
     */
    public function scopeForStripePrices(Builder $query, array $priceIds): Builder
    {
        return $query->whereHas('prices', fn (Builder $query): Builder => $query->whereIn('stripe_price_id', $priceIds));
    }
}
