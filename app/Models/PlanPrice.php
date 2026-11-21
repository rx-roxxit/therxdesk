<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\Interval;
use Database\Factories\PlanPriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One price of a Plan, monthly or yearly. A price change deactivates the old
 * row and adds a new one, so a Stripe price id an old subscription still holds
 * keeps resolving to its plan.
 */
class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'plan_id',
        'interval',
        'amount',
        'stripe_price_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval' => Interval::class,
            'amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
