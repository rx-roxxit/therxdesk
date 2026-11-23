<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Number;

/**
 * The fixed Thai purchase document shown to a buyer whose checkout was billed
 * to Thailand, and carried by the order-confirmation email. It is the one Thai
 * copy in the product (the UI ships 16 other locales), so its text lives here,
 * not in `rxdesk.php`.
 *
 * It reads the current Operator (the Thai legal name and address) and the
 * Account's settled subscription when it is built, so changing the Operator
 * changes the document. Null when the Account was billed outside Thailand, so
 * every other buyer gets nothing.
 */
final class ThaiPurchaseDocument
{
    public const string COUNTRY = 'TH';

    /**
     * The DSDMA's seven-day cancellation right (ss. 33 and 36): written
     * notice to the seller, full refund within fifteen days.
     */
    public const string CANCELLATION_TEXT = 'ผู้ซื้อมีสิทธิ์ยกเลิกสัญญาได้ภายใน 7 วันนับจากวันที่ได้รับสินค้าหรือบริการ โดยแจ้งเป็นหนังสือถึงผู้ขาย และผู้ขายต้องคืนเงินที่ชำระมาทั้งหมดภายใน 15 วันนับจากวันที่ได้รับแจ้ง';

    /**
     * @return array{
     *     title: string,
     *     rows: list<array{label: string, value: string}>,
     *     cancellation: string
     * }|null
     */
    public static function for(Account $account): ?array
    {
        if (! self::applies($account)) {
            return null;
        }

        $operator = Operator::current();

        if ($operator === null) {
            return null;
        }

        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);
        $purchaseAt = $account->checkout_completed_at ?? $account->created_at ?? now();
        $onTrial = $subscription?->onTrial() ?? false;
        $serviceStart = $onTrial ? self::serviceStart($subscription, $purchaseAt) : $purchaseAt;
        $firstChargeAt = $onTrial ? self::firstChargeAt($subscription, $serviceStart) : $purchaseAt;

        return [
            'title' => 'เอกสารการซื้อสินค้าและบริการ',
            'rows' => [
                ['label' => 'ผู้ขาย', 'value' => $operator->legal_name_th],
                ['label' => 'ที่อยู่ผู้ขาย', 'value' => $operator->address_th],
                ['label' => 'ผู้ซื้อ', 'value' => self::buyerName($account)],
                ['label' => 'วันที่ซื้อ', 'value' => self::thaiDate($purchaseAt)],
                ['label' => 'วันที่เริ่มให้บริการ', 'value' => self::thaiDate($serviceStart)],
                ['label' => 'ราคาหลังสิ้นสุดระยะทดลองใช้', 'value' => self::priceAfterTrial($account, $subscription)],
                ['label' => 'วันที่เรียกเก็บเงินครั้งแรก', 'value' => self::thaiDate($firstChargeAt)],
            ],
            'cancellation' => self::CANCELLATION_TEXT,
        ];
    }

    public static function applies(Account $account): bool
    {
        return $account->billing_country === self::COUNTRY;
    }

    private static function buyerName(Account $account): string
    {
        if (filled($account->billing_name)) {
            return $account->billing_name;
        }

        return (string) $account->owner?->name;
    }

    private static function serviceStart(?Subscription $subscription, CarbonInterface $fallback): CarbonInterface
    {
        return $subscription?->trial_started_at
            ?? $subscription?->created_at
            ?? $fallback;
    }

    private static function firstChargeAt(?Subscription $subscription, CarbonInterface $fallback): CarbonInterface
    {
        return $subscription?->trial_ends_at ?? $fallback;
    }

    /**
     * The recurring amount the first charge after the Trial is billed, with
     * the redeemed Offer's discount when it covers the Plan and interval.
     */
    private static function priceAfterTrial(Account $account, ?Subscription $subscription): string
    {
        $priceId = $subscription?->stripe_price;

        $plan = $account->plan;

        if ($plan === null && filled($priceId)) {
            $plan = Plan::forStripePrices([$priceId])->with('prices')->first();
        }

        if ($plan === null) {
            return '—';
        }

        $interval = $plan->intervalFor($priceId) ?? Interval::Monthly;
        $amount = self::amountFor($plan, $priceId, $interval);

        if ($amount === null) {
            return '—';
        }

        $offer = $account->latestRedeemedOffer();

        if ($offer !== null && $offer->hasCoupon() && $offer->covers($plan, $interval)) {
            $amount = OfferPricing::discountedAmount($amount, $offer);
        }

        return Number::currency($amount / 100, 'USD', locale: 'en').' '.self::period($interval);
    }

    private static function amountFor(Plan $plan, ?string $priceId, Interval $interval): ?int
    {
        if (filled($priceId)) {
            $amount = $plan->prices()->where('stripe_price_id', $priceId)->value('amount');

            if ($amount !== null) {
                return (int) $amount;
            }
        }

        $amount = $plan->priceFor($interval)?->amount;

        return $amount === null ? null : (int) $amount;
    }

    private static function period(Interval $interval): string
    {
        return $interval === Interval::Yearly ? 'ต่อปี' : 'ต่อเดือน';
    }

    /**
     * A Gregorian date in the Thai Buddhist Era, e.g. "23 พฤศจิกายน 2569".
     */
    private static function thaiDate(CarbonInterface $date): string
    {
        $months = [
            1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม',
        ];

        $local = $date->copy()->timezone(config('app.timezone', 'UTC'));

        return sprintf('%d %s %d', $local->day, $months[$local->month], $local->year + 543);
    }
}
