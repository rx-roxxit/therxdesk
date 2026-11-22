<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\Interval;
use App\Enums\Billing\OfferDiscountType;
use App\Enums\Billing\OfferDuration;
use App\Enums\Billing\OfferReach;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * An admin-managed campaign. The row is the only source of truth for its
 * dates, places and coverage; the Stripe coupon carries the discount but no
 * dates and no redemption limit. Discount, duration and coverage never change
 * after creation, and an ended Offer is read-only.
 */
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'reach',
        'discount_type',
        'percent_off',
        'amount_off',
        'duration',
        'duration_in_months',
        'stripe_coupon_id',
        'trial_days',
        'starts_at',
        'ends_at',
        'max_redemptions',
        'show_banner',
        'plan_ids',
        'intervals',
    ];

    protected function casts(): array
    {
        return [
            'reach' => OfferReach::class,
            'discount_type' => OfferDiscountType::class,
            'percent_off' => 'float',
            'amount_off' => 'integer',
            'duration' => OfferDuration::class,
            'duration_in_months' => 'integer',
            'trial_days' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_redemptions' => 'integer',
            'show_banner' => 'boolean',
            'plan_ids' => 'array',
            'intervals' => 'array',
        ];
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)->withPivot('claimed_at', 'redeemed_at');
    }

    /**
     * The accounts that redeemed the offer. Each one uses up a place.
     */
    public function redemptions(): BelongsToMany
    {
        return $this->accounts()->wherePivotNotNull('redeemed_at');
    }

    /**
     * Started, not ended, and with a place left. Only redemptions use up a
     * place; a claim alone never does.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->whereNowOrPast('offers.starts_at')
            ->where(fn (Builder $query): Builder => $query->whereNull('offers.ends_at')->orWhereFuture('offers.ends_at'))
            ->where(fn (Builder $query): Builder => $query->whereNull('offers.max_redemptions')->orHas('redemptions', '<', DB::raw('offers.max_redemptions')));
    }

    /**
     * Available offers the account may still use: never one it redeemed.
     */
    public function scopeAvailableTo(Builder $query, Account $account): Builder
    {
        return $query
            ->available()
            ->whereDoesntHave('redemptions', fn (Builder $query): Builder => $query->whereKey($account->id));
    }

    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('offers.reach', OfferReach::General->value);
    }

    public function scopeLink(Builder $query): Builder
    {
        return $query->where('offers.reach', OfferReach::Link->value);
    }

    /**
     * Among general Offers, the one that started last wins; a tie goes to the
     * one created last.
     */
    public function scopeLatestStarted(Builder $query): Builder
    {
        return $query->orderByDesc('offers.starts_at')->orderByDesc('offers.created_at');
    }

    public function hasCoupon(): bool
    {
        return filled($this->stripe_coupon_id) && filled($this->discount_type);
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    /**
     * An Offer whose end has come is read-only: to extend a campaign, create
     * a new Offer.
     */
    public function hasEnded(): bool
    {
        return filled($this->ends_at) && $this->ends_at->isPast();
    }

    /**
     * Whether the Offer covers this Plan and interval. A legacy row written
     * before coverage existed covers every Plan monthly.
     */
    public function covers(Plan $plan, Interval $interval): bool
    {
        if (filled($this->plan_ids) && ! in_array((string) $plan->id, $this->plan_ids, true)) {
            return false;
        }

        return in_array($interval->value, $this->coveredIntervals(), true);
    }

    /**
     * @return list<string>
     */
    public function coveredIntervals(): array
    {
        if (filled($this->intervals)) {
            return array_values($this->intervals);
        }

        return [Interval::Monthly->value];
    }

    /**
     * Offers without a trial: a coupon only, for a checkout that charges at
     * once.
     */
    public function scopeWithoutTrial(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query->whereNull('offers.trial_days')->orWhere('offers.trial_days', 0));
    }

    /**
     * The available link Offer with exactly this slug, for an account when one
     * is given. MySQL compares strings without case, so the match is confirmed
     * here, where both engines agree. A general Offer is never reachable by a
     * link.
     */
    public static function findAvailable(string $slug, ?Account $account = null): ?self
    {
        $offers = filled($account) ? self::availableTo($account) : self::available();

        return $offers->link()->where('slug', $slug)->get()->firstWhere('slug', $slug);
    }
}
