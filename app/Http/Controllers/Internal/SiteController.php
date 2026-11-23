<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Enums\Availability\ServiceSwitch;
use App\Enums\Billing\Interval;
use App\Enums\SocialAccount\Platform;
use App\Enums\User\Locale;
use App\Http\Resources\Internal\SitePlanResource;
use App\Models\CatalogSetting;
use App\Models\Offer;
use App\Models\Operator;
use App\Models\Plan;
use App\Support\Billing\OfferCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class SiteController
{
    public function __invoke(Request $request): JsonResponse
    {
        $plans = Plan::query()
            ->active()
            ->orderBy('sort')
            ->orderBy('name')
            ->with('prices')
            ->get();

        $offer = Offer::query()
            ->available()
            ->general()
            ->latestStarted()
            ->first();

        return response()->json([
            'plans' => $plans
                ->map(fn (Plan $plan): array => SitePlanResource::make($plan, $offer)->resolve($request))
                ->all(),
            'trial_days' => CatalogSetting::current()->trial_days,
            'offer' => $this->offer($offer),
            'networks' => $this->networks(),
            'signups_open' => ! ServiceSwitch::newSignupsClosed(),
            'operator' => $this->operator(Operator::current()),
        ]);
    }

    /**
     * The network keys a customer can connect right now: installed and
     * switched on. Variants collapse the way the accounts grid's connect
     * cards do, so a LinkedIn page sits under LinkedIn and Instagram via
     * Facebook under Instagram.
     *
     * @return list<string>
     */
    private function networks(): array
    {
        return Arr::pluck(Platform::connectableOptions(), 'value');
    }

    /**
     * The customer-facing fields of the Operator the website must name. Phone
     * and the ids stay internal.
     *
     * @return array<string, mixed>|null
     */
    private function operator(?Operator $operator): ?array
    {
        if ($operator === null) {
            return null;
        }

        return [
            'legal_name_latin' => $operator->legal_name_latin,
            'address_latin' => $operator->address_latin,
            'legal_name_th' => $operator->legal_name_th,
            'address_th' => $operator->address_th,
            'tax_id' => $operator->tax_id,
            'vat_id' => $operator->vat_id,
            'contact_email' => $operator->contact_email,
            'effective_from' => $operator->effective_from,
        ];
    }

    /**
     * The running general Offer, as data the website can price and format in
     * the visitor's locale. A link Offer is never published here.
     *
     * @return array<string, mixed>|null
     */
    private function offer(?Offer $offer): ?array
    {
        if ($offer === null) {
            return null;
        }

        return [
            'slug' => $offer->slug,
            'name' => $offer->name,
            'banner' => $this->banner($offer),
            'trial_days' => $offer->trial_days,
            'starts_at' => $offer->starts_at,
            'ends_at' => $offer->ends_at,
            'discount' => $offer->hasCoupon() ? [
                'type' => $offer->discount_type->value,
                'percent_off' => $offer->percent_off,
                'amount_off' => $offer->amount_off,
                'currency' => 'usd',
                'duration' => $offer->duration?->value,
                'duration_in_months' => $offer->duration_in_months,
            ] : null,
            'plan_ids' => $offer->plan_ids,
            'intervals' => $offer->coveredIntervals(),
        ];
    }

    /**
     * The Offer's banner sentence in every locale, so the website can show it
     * in the visitor's language without translating the campaign name. The
     * locale is restored before the response, whatever happens.
     *
     * @return array<string, string>
     */
    private function banner(Offer $offer): array
    {
        $original = App::currentLocale();

        try {
            $banner = [];

            foreach (Locale::cases() as $locale) {
                App::setLocale($locale->value);
                $banner[$locale->value] = OfferCopy::line($offer, Interval::Monthly);
            }
        } finally {
            App::setLocale($original);
        }

        return $banner;
    }
}
