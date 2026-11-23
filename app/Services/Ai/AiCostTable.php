<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Billing\Interval;
use App\Models\CatalogSetting;
use App\Models\Offer;
use App\Models\Plan;
use App\Support\Billing\OfferPricing;

/**
 * The worst-case AI cost of every active Plan: what its whole monthly AI
 * allowance costs if every token were output. Computed live on every read,
 * never cached, so a Plan credit change, a token-rate change, a new text model
 * or a running general Offer moves it at once.
 *
 * The 10% warning uses the unrounded product; the cents shown are rounded for
 * display only.
 */
final class AiCostTable
{
    public function __construct(
        private readonly AiModelRegistry $registry,
        private readonly AiSettings $settings,
    ) {}

    /**
     * @return array{
     *     tokens_per_credit: int,
     *     text_model: string,
     *     output_price_per_mtok: float|null,
     *     general_offer: array{id: string, slug: string, name: string}|null,
     *     plans: list<array{id: string, slug: string, name: string, ai_credits_per_month: int|null, monthly_cents: int|null, discounted_monthly_cents: int|null, worst_case_cents: int|null, threshold_cents: int|null, over_threshold: bool}>,
     *     over_threshold: bool
     * }
     */
    public function build(?string $textModel = null, ?int $tokensPerCredit = null): array
    {
        $setting = CatalogSetting::current();
        $tokens = $tokensPerCredit ?? (int) $setting->tokens_per_credit;
        $model = $textModel ?? $this->settings->textModel();
        $prices = $this->registry->find($model);
        $outputPrice = $prices === null ? null : $prices['output_price_per_mtok'];
        $offer = Offer::query()->available()->general()->latestStarted()->first();

        $plans = Plan::query()
            ->active()
            ->orderBy('sort')
            ->orderBy('name')
            ->with('prices')
            ->get();

        $rows = $plans
            ->map(fn (Plan $plan): array => $this->row($plan, $offer, $tokens, $outputPrice))
            ->all();

        return [
            'tokens_per_credit' => $tokens,
            'text_model' => $model,
            'output_price_per_mtok' => $outputPrice,
            'general_offer' => $this->offerSnapshot($offer),
            'plans' => $rows,
            'over_threshold' => collect($rows)->contains(fn (array $row): bool => $row['over_threshold']),
        ];
    }

    /**
     * @return array{id: string, slug: string, name: string, ai_credits_per_month: int|null, monthly_cents: int|null, discounted_monthly_cents: int|null, worst_case_cents: int|null, threshold_cents: int|null, over_threshold: bool}
     */
    private function row(Plan $plan, ?Offer $offer, int $tokens, ?float $outputPrice): array
    {
        $monthly = $plan->priceFor(Interval::Monthly)?->amount;
        $monthlyCents = $monthly === null ? null : (int) $monthly;
        $discounted = $monthlyCents;
        $covered = $offer === null ? null : OfferPricing::forPlan($offer, $plan, Interval::Monthly);

        if ($covered !== null) {
            $discounted = $covered['discounted'];
        }

        $credits = $plan->ai_credits_per_month;

        return [
            'id' => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
            'ai_credits_per_month' => $credits,
            'monthly_cents' => $monthlyCents,
            'discounted_monthly_cents' => $discounted,
            'worst_case_cents' => $this->worstCase($credits, $tokens, $outputPrice),
            'threshold_cents' => $discounted === null ? null : intdiv($discounted, 10),
            'over_threshold' => $this->passesThreshold($credits, $tokens, $outputPrice, $discounted),
        ];
    }

    private function worstCase(?int $credits, int $tokens, ?float $outputPrice): ?int
    {
        if ($credits === null || $outputPrice === null) {
            return null;
        }

        return (int) round($credits * $tokens * $outputPrice / 10000);
    }

    private function passesThreshold(?int $credits, int $tokens, ?float $outputPrice, ?int $discounted): bool
    {
        if ($credits === null || $outputPrice === null || $discounted === null) {
            return false;
        }

        return $credits * $tokens * $outputPrice * 10 > $discounted * 10000;
    }

    /**
     * @return array{id: string, slug: string, name: string}|null
     */
    private function offerSnapshot(?Offer $offer): ?array
    {
        if ($offer === null) {
            return null;
        }

        return [
            'id' => $offer->id,
            'slug' => $offer->slug,
            'name' => $offer->name,
        ];
    }
}
