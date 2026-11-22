<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\Billing\Interval;
use App\Enums\Billing\OfferDiscountType;
use App\Enums\Billing\OfferDuration;
use App\Models\Offer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;

/**
 * An Offer's customer copy comes from templates in `rxdesk.php`, built from
 * the row's own fields: a campaign has no per-Offer lang key, and the 16
 * locales only carry one key per duration, interval and discount shape.
 */
final class OfferCopy
{
    /**
     * The discount as the user reads it: "20%" or "$5.00", in their locale.
     */
    public static function discountLabel(Offer $offer): ?string
    {
        if (! $offer->hasCoupon()) {
            return null;
        }

        $locale = App::currentLocale();

        if ($offer->discount_type === OfferDiscountType::Percent) {
            return Number::percentage($offer->percent_off, maxPrecision: 2, locale: $locale);
        }

        return self::money((int) $offer->amount_off, $locale);
    }

    /**
     * The Offer's line: "20% off your first 3 months", "35% off for as long
     * as you stay subscribed, until Mar 1", or the trial it grants.
     */
    public static function line(Offer $offer, Interval $interval, ?CarbonInterface $now = null): string
    {
        $now ??= now();

        if (! $offer->hasCoupon()) {
            return self::trialLine($offer);
        }

        $discount = self::discountLabel($offer);
        $monthly = $interval === Interval::Monthly;

        if ($offer->duration === OfferDuration::Repeating) {
            return self::repeatingLine($offer, $monthly, $discount);
        }

        if ($offer->duration === OfferDuration::Once) {
            return __($monthly ? 'rxdesk.offer.line.once_monthly' : 'rxdesk.offer.line.once_yearly', [
                'discount' => $discount,
            ]);
        }

        if (filled($offer->ends_at)) {
            return __('rxdesk.offer.line.forever_until', [
                'discount' => $discount,
                'date' => self::date($offer->ends_at, $now),
            ]);
        }

        return __('rxdesk.offer.line.forever', ['discount' => $discount]);
    }

    /**
     * A price in USD cents, written in the app's locale with two decimals.
     */
    public static function money(int $cents, ?string $locale = null): string
    {
        return Number::currency($cents / 100, 'USD', $locale ?? App::currentLocale());
    }

    /**
     * A date a reader recognizes: "Mar 1", with the year when it is not this
     * one. The year keeps an Offer that runs across a New Year unambiguous.
     */
    public static function date(CarbonInterface $date, ?CarbonInterface $now = null): string
    {
        $now ??= now();
        $format = $date->year === $now->year ? 'M j' : 'M j, Y';

        return $date->locale(App::currentLocale())->translatedFormat($format);
    }

    private static function repeatingLine(Offer $offer, bool $monthly, ?string $discount): string
    {
        if (! $monthly) {
            return __('rxdesk.offer.line.repeating_yearly', ['discount' => $discount]);
        }

        $months = max(1, (int) $offer->duration_in_months);

        return trans_choice('rxdesk.offer.line.repeating_monthly', $months, [
            'discount' => $discount,
            'months' => $months,
        ]);
    }

    private static function trialLine(Offer $offer): string
    {
        if (! $offer->hasTrial()) {
            return '';
        }

        return trans_choice('rxdesk.offer.line.trial', (int) $offer->trial_days, [
            'days' => $offer->trial_days,
        ]);
    }
}
