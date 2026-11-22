<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\Offer;
use App\Models\Plan;
use App\Support\Billing\ConfigureSubscriptionCheckout;
use App\Support\Billing\OfferPlanPrices;
use App\Support\Billing\OfferPricing;
use App\Support\ByteSize;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * A Plan on the Welcome plan step: PlanResource plus the two labels the cards
 * show, its prices written in the reader's locale, and, per interval, the
 * Offer's badge, struck price, discounted price and line for a Plan the Offer
 * covers.
 *
 * @mixin Plan
 */
class WelcomePlanResource extends JsonResource
{
    public function __construct(
        Plan $resource,
        private readonly Account $account,
        private readonly ?Offer $offer = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...PlanResource::make($this->resource)->resolve($request),
            'quota_label' => filled($this->quota_bytes) ? ByteSize::format((int) $this->quota_bytes) : null,
            'ai_credits_label' => filled($this->ai_credits_per_month) ? Number::format((int) $this->ai_credits_per_month) : null,
            'offer' => [
                Interval::Monthly->value => $this->offerSlot(Interval::Monthly),
                Interval::Yearly->value => $this->offerSlot(Interval::Yearly),
            ],
            'trial' => [
                Interval::Monthly->value => $this->trialSlot(Interval::Monthly),
                Interval::Yearly->value => $this->trialSlot(Interval::Yearly),
            ],
        ];
    }

    /**
     * The Offer's price for one interval, only on a Plan and interval it
     * covers and only when it actually discounts. A trial-only Offer shows no
     * struck price; its trial still comes from `trialSlot()`.
     *
     * @return array{badge: string, regular: string, discounted: string, line: string}|null
     */
    private function offerSlot(Interval $interval): ?array
    {
        if ($this->offer === null) {
            return null;
        }

        return OfferPlanPrices::slot(
            $this->offer,
            OfferPricing::forPlan($this->offer, $this->resource, $interval),
            $interval,
        );
    }

    /**
     * The trial this Plan and interval gets: the longer Offer trial only when
     * the Offer covers them, else the Catalog's. Null once the Account has
     * had a trial.
     *
     * @return array{days: int, first_charge_at: string}|null
     */
    private function trialSlot(Interval $interval): ?array
    {
        $offer = null;

        if ($this->offer !== null && $this->offer->covers($this->resource, $interval)) {
            $offer = $this->offer;
        }

        $days = ConfigureSubscriptionCheckout::trialDays($offer, $this->account);

        if ($days <= 0) {
            return null;
        }

        return [
            'days' => $days,
            'first_charge_at' => now()->addDays($days)->toIso8601String(),
        ];
    }
}
