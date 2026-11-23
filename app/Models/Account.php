<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Account\AccessDenial;
use App\Enums\Account\DriveConnection;
use App\Enums\Account\LifecycleStage;
use App\Enums\Account\VideoPolicy;
use App\Enums\Media\Source;
use App\Models\Traits\BillsOwnerAsStripeCustomer;
use App\Models\Traits\HasUsage;
use Carbon\CarbonInterface;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use BillsOwnerAsStripeCustomer, HasFactory, HasUsage, HasUuids;

    public const SUBSCRIPTION_NAME = 'default';

    /**
     * How long an Account's data stays after access stops. Written into the
     * Terms; never an env value.
     */
    public const int DELETION_GRACE_DAYS = 30;

    public static function postsCountCacheKey(string $accountId): string
    {
        return "account:{$accountId}:posts_count";
    }

    protected $fillable = [
        'owner_id',
        'name',
        'plan_id',
        'ai_credits_per_month',
        'trial_ends_at',
        'complimentary_since',
        'complimentary_until',
        'first_invoice_id',
        'blocked_country',
        'blocked_country_at',
        'blocked_country_payment_status',
    ];

    /**
     * Internal admin facts never leave the System admin's surfaces.
     *
     * @var list<string>
     */
    protected $hidden = [
        'suspension_reason',
        'review_access',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
        'complimentary_since' => 'datetime',
        'complimentary_until' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'onboarding_dismissed_at' => 'datetime',
        'onboarding_skipped_steps' => 'array',
        'lifecycle_stage' => LifecycleStage::class,
        'lifecycle_stage_changed_at' => 'datetime',
        'activated_at' => 'datetime',
        'first_paid_at' => 'datetime',
        'blocked_country_at' => 'datetime',
        'deletion_scheduled_at' => 'datetime',
        'data_purged_at' => 'datetime',
        'video_policy' => VideoPolicy::class,
        'ai_credits_per_month' => 'integer',
        'ai_credits_reset_at' => 'datetime',
        'review_access' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The Owner's Google Drive sign-in for the Video policy's Backup.
     */
    public function driveBackupConnection(): ?MediaSourceConnection
    {
        return MediaSourceConnection::query()
            ->where('user_id', $this->owner_id)
            ->where('source', Source::GoogleDriveBackup)
            ->first();
    }

    public function driveConnection(): DriveConnection
    {
        $connection = $this->driveBackupConnection();

        if ($connection === null) {
            return DriveConnection::None;
        }

        if ($connection->needsReconnect()) {
            return DriveConnection::NeedsReconnect;
        }

        return DriveConnection::Connected;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    public function lifecycleEmails(): HasMany
    {
        return $this->hasMany(LifecycleEmail::class);
    }

    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class)->withPivot('claimed_at', 'redeemed_at');
    }

    /**
     * The offers this account used: a checkout with them became active or
     * trialing, or their trial without a card started.
     */
    public function redeemedOffers(): BelongsToMany
    {
        return $this->offers()->wherePivotNotNull('redeemed_at');
    }

    /**
     * The one offer a checkout uses. A link Offer the account claimed wins
     * over a general one; a general Offer needs no claim and the latest one to
     * start wins. Without one the account pays the full price. Subscribing
     * from billing leaves trials out, since it charges at once.
     */
    public function currentOffer(bool $withTrial = true): ?Offer
    {
        return $this->claimedOffer($withTrial) ?? $this->generalOffer($withTrial);
    }

    private function claimedOffer(bool $withTrial): ?Offer
    {
        return $this->offers()
            ->availableTo($this)
            ->link()
            ->unless($withTrial, fn (Builder $query): Builder => $query->withCoupon())
            ->orderByPivot('claimed_at', 'desc')
            ->first();
    }

    private function generalOffer(bool $withTrial): ?Offer
    {
        return Offer::query()
            ->availableTo($this)
            ->general()
            ->unless($withTrial, fn (Builder $query): Builder => $query->withCoupon())
            ->latestStarted()
            ->first();
    }

    /**
     * The Offer the current subscription was bought with: the last one it
     * redeemed. A plan change keeps its discount only while the Offer covers
     * the new Plan and interval.
     */
    public function latestRedeemedOffer(): ?Offer
    {
        return $this->redeemedOffers()
            ->orderByPivot('redeemed_at', 'desc')
            ->first();
    }

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, Workspace::class);
    }

    public function socialAccounts(): HasManyThrough
    {
        return $this->hasManyThrough(SocialAccount::class, Workspace::class);
    }

    /**
     * The aggregates the lifecycle stage is resolved from: whether any channel
     * exists, whatever its status, and the first and last posts TryPost published.
     */
    public function scopeWithLifecycleActivity(Builder $query): Builder
    {
        $publishedByTryPost = fn (Builder $posts): Builder => $posts->createdInTryPost()->wentOut();

        return $query
            ->withExists('socialAccounts as has_social_account')
            ->withMin(['posts as first_published_at' => $publishedByTryPost], 'published_at')
            ->withMax(['posts as last_published_at' => $publishedByTryPost], 'published_at')
            ->withCasts([
                'has_social_account' => 'boolean',
                'first_published_at' => 'datetime',
                'last_published_at' => 'datetime',
            ]);
    }

    public function hasHadBillableSubscription(): bool
    {
        return $this->subscriptions->contains(fn (Subscription $subscription): bool => $subscription->becameBillable());
    }

    /**
     * Used a trial before: a generic one (`accounts.trial_ends_at`), a
     * subscription that started on a trial, or an offer with trial days it
     * redeemed. One trial per Account.
     */
    public function hasHadTrial(): bool
    {
        if (filled($this->trial_ends_at)) {
            return true;
        }

        if ($this->subscriptions()
            ->where(fn (Builder $query): Builder => $query->whereNotNull('trial_ends_at')->orWhereNotNull('trial_started_at'))
            ->exists()) {
            return true;
        }

        return $this->redeemedOffers()->where('trial_days', '>', 0)->exists();
    }

    /**
     * A Complimentary Plan is in force: given by the System admin, and either
     * without an end date or before one that has not passed.
     */
    public function isComplimentary(): bool
    {
        if (blank($this->complimentary_since)) {
            return false;
        }

        if (blank($this->complimentary_until)) {
            return true;
        }

        return $this->complimentary_until->isFuture();
    }

    /**
     * Was ever given a Complimentary Plan. The `since` column is never
     * cleared, so this outlives the Plan and its end date.
     */
    public function hadComplimentaryPlan(): bool
    {
        return filled($this->complimentary_since);
    }

    public function complimentaryEndsAt(): ?CarbonInterface
    {
        return $this->complimentary_until;
    }

    /**
     * A first checkout may start a trial: never one before, never charged,
     * and no billable subscription without a trial. A Complimentary Plan
     * rules a Trial out for good: the System admin ends it first, then the
     * Account pays.
     */
    public function canStartTrial(): bool
    {
        if ($this->hadComplimentaryPlan()) {
            return false;
        }

        if ($this->hasHadTrial()) {
            return false;
        }

        if (filled($this->first_paid_at)) {
            return false;
        }

        return ! $this->subscriptions()->get()->contains(
            fn (Subscription $subscription): bool => $subscription->becameBillable() && blank($subscription->trial_ends_at),
        );
    }

    /**
     * Charged at least once. Cashier clears `trial_ends_at` when a subscription
     * is deleted, so a trial cancelled before its first charge cannot be told
     * apart from a paid one afterwards; `first_paid_at` keeps that fact.
     */
    public function hasPaidSubscription(): bool
    {
        return filled($this->first_paid_at);
    }

    /**
     * A paying Account whose subscription ended: Stripe cancelled it after
     * Smart Retries ran out. It was charged at least once, has no access now
     * and is not on a generic trial.
     */
    public function isLapsed(): bool
    {
        if (config('trypost.self_hosted')) {
            return false;
        }

        if ($this->isComplimentary()) {
            return false;
        }

        if ($this->subscribed(self::SUBSCRIPTION_NAME)) {
            return false;
        }

        if ($this->onGenericTrial()) {
            return false;
        }

        // A Complimentary Plan that ended is a lapse even though the Account
        // never paid: that is the path it follows.
        if ($this->hadComplimentaryPlan()) {
            return true;
        }

        return filled($this->first_paid_at);
    }

    public function hasActiveSubscription(): bool
    {
        if (config('trypost.self_hosted')) {
            return true;
        }

        return $this->subscribed(self::SUBSCRIPTION_NAME);
    }

    /**
     * Never had a Subscription at all, and never a Complimentary Plan. That
     * signup is deleted entirely 30 days after signup, with no email. A
     * Complimentary Account is not one of these: it keeps its User and follows
     * the Lapsed path when its Plan ends (ADR 0006).
     */
    public function isNeverPaid(): bool
    {
        if ($this->hadComplimentaryPlan()) {
            return false;
        }

        return ! $this->hasEverSubscribed();
    }

    public function hasEverSubscribed(): bool
    {
        return $this->subscriptions()->exists();
    }

    public function deletionIsDue(): bool
    {
        return filled($this->deletion_scheduled_at) && $this->deletion_scheduled_at->isPast();
    }

    /**
     * Whole days until the deletion clock fires, zero once it has passed.
     */
    public function deletionDaysLeft(): ?int
    {
        if (blank($this->deletion_scheduled_at)) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInDays($this->deletion_scheduled_at, false)));
    }

    public function subscriptionStatus(): string
    {
        $subscription = $this->subscription(self::SUBSCRIPTION_NAME);

        if (! $subscription) {
            return 'none';
        }

        return $subscription->canceled() ? 'canceled' : $subscription->stripe_status;
    }

    /**
     * The plan holds the price of the current subscription: what a billing
     * checkout waits for, so Processing never returns to billing on the
     * plan the account had before.
     */
    public function planMatchesSubscription(): bool
    {
        $subscription = $this->validSubscription();

        return filled($subscription)
            && Plan::forStripePrices([$subscription->stripe_price])->whereKey($this->plan_id)->exists();
    }

    /**
     * The subscription while it still gives access (what `subscribed()`
     * checks): active, trialing or in its grace period.
     */
    public function validSubscription(): ?Subscription
    {
        $subscription = $this->subscription(self::SUBSCRIPTION_NAME);

        return $subscription?->valid() ? $subscription : null;
    }

    /**
     * The first charge after a Trial failed and was never paid: the account is
     * held at the retry step until the card works. A subscription that was
     * charged before keeps its access while Stripe retries.
     */
    public function hasPendingFirstCharge(): bool
    {
        if (config('trypost.self_hosted')) {
            return false;
        }

        if (filled($this->first_paid_at)) {
            return false;
        }

        return (bool) $this->subscription(self::SUBSCRIPTION_NAME)?->pastDue();
    }

    /**
     * The System admin let this Account use networks that are installed but
     * switched off (a platform reviewer's test Account). It never unlocks a
     * network the server has no credentials for.
     */
    public function hasReviewAccess(): bool
    {
        return (bool) $this->review_access;
    }

    /**
     * The System admin turned the Account off. It outranks every other state,
     * self-hosted included.
     */
    public function isSuspended(): bool
    {
        return filled($this->suspended_at);
    }

    /**
     * Why the Account may not use the app right now, or null when it may.
     */
    public function accessDenial(): ?AccessDenial
    {
        if ($this->isSuspended()) {
            return AccessDenial::Suspended;
        }

        if ($this->hasAppAccess()) {
            return null;
        }

        return AccessDenial::Lapsed;
    }

    public function hasAppAccess(): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        if (config('trypost.self_hosted')) {
            return true;
        }

        if (filled($this->blocked_country_at)) {
            return false;
        }

        if ($this->hasPendingFirstCharge()) {
            return false;
        }

        return $this->subscribed(self::SUBSCRIPTION_NAME)
            || $this->onGenericTrial()
            || $this->isComplimentary();
    }

    /**
     * The AI credits the Account gets every month: its own override, else its
     * Plan's. Null when neither is set.
     */
    public function aiCreditsPerMonth(): ?int
    {
        if (filled($this->ai_credits_per_month)) {
            return (int) $this->ai_credits_per_month;
        }

        return $this->plan?->ai_credits_per_month;
    }

    public function workspaceLimit(): ?int
    {
        if (config('trypost.self_hosted')) {
            return null;
        }

        if ($this->plan === null) {
            return 1;
        }

        return $this->plan->workspace_limit;
    }

    public function canCreateWorkspace(): bool
    {
        if (config('trypost.self_hosted')) {
            return true;
        }

        $limit = $this->workspaceLimit();

        if ($limit === null) {
            return true;
        }

        return $this->workspaces()->count() < $limit;
    }

    public function isPastDue(): bool
    {
        if (config('trypost.self_hosted')) {
            return false;
        }

        return (bool) $this->subscription(self::SUBSCRIPTION_NAME)?->pastDue();
    }

    public function isOnTrial(): bool
    {
        return $this->onGenericTrial() || (bool) $this->subscription(self::SUBSCRIPTION_NAME)?->onTrial();
    }

    public function activeTrialEndsAt(): ?CarbonInterface
    {
        $subscription = $this->subscription(self::SUBSCRIPTION_NAME);

        if ($subscription?->onTrial()) {
            return $subscription->trial_ends_at;
        }

        return $this->onGenericTrial() ? $this->trial_ends_at : null;
    }
}
