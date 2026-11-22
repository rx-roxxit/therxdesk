<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Billing\ChangePlan;
use App\Actions\Billing\ListAccountCards;
use App\Actions\Billing\ShowSubscriptionDiscount;
use App\Enums\Billing\CheckoutOrigin;
use App\Enums\Billing\Interval;
use App\Http\Requests\App\Billing\ChangePlanRequest;
use App\Http\Resources\App\InvoiceResource;
use App\Http\Resources\App\PlanResource;
use App\Models\Account;
use App\Models\Plan;
use App\Support\Billing\BillingPortal;
use App\Support\Billing\OfferCopy;
use App\Support\Billing\OfferPlanPrices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function subscribe(): RedirectResponse
    {
        return redirect()->route('app.welcome.plan');
    }

    public function processing(Request $request): Response|RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $account = $request->user()->accountOrFail();
        $returnsToBilling = $request->query('from') === CheckoutOrigin::Billing->value;

        if ($account->hasPendingFirstCharge()) {
            return redirect()->route('app.welcome.plan');
        }

        return Inertia::render('billing/Processing', [
            'ready' => $returnsToBilling ? $account->planMatchesSubscription() : $account->hasAppAccess(),
            'returnsToBilling' => $returnsToBilling,
            'countryBlocked' => filled($account->blocked_country_at),
        ]);
    }

    public function index(Request $request): Response|RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $account = $request->user()->account;

        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        $subscription = $account->validSubscription();
        $resolved = false;
        $discountValue = null;
        $discount = function () use ($account, $subscription, &$resolved, &$discountValue): ?array {
            if (! $resolved) {
                $discountValue = ShowSubscriptionDiscount::execute($account, $subscription);
                $resolved = true;
            }

            return $discountValue;
        };

        return Inertia::render('settings/account/Billing', [
            'hasSubscription' => filled($subscription),
            'onTrial' => $account->isOnTrial(),
            'trialEndsAt' => $account->activeTrialEndsAt(),
            'subscription' => $subscription?->only(['stripe_status', 'ends_at']),
            'plan' => transform($account->plan, fn (Plan $plan): array => PlanResource::make($plan)->resolve()),
            'workspaceCount' => $account->workspaces()->count(),
            'invoices' => Inertia::scroll(fn () => InvoiceResource::collection(
                $account->cursorPaginateInvoices((int) config('app.pagination.default'), ['status' => 'paid']),
            ))->matchOn('data.id'),
            'paymentMethods' => fn (): array => ListAccountCards::execute($account, $subscription),
            'discount' => $discount,
            'changePlanOffer' => fn (): ?array => $this->changePlanOffer($account, $discount()),
        ]);
    }

    /**
     * The Offer the current subscription was bought with, priced for every
     * active Plan × interval it covers, so the change-plan cards show what the
     * next invoice would be. Without a live discount there is nothing to
     * show: the Offer is the only source of a discount.
     *
     * @param  array{value: string, duration: string, ends_at: string|null}|null  $discount
     * @return array{name: string, discount_label: string|null, plans: array<string, mixed>}|null
     */
    private function changePlanOffer(Account $account, ?array $discount): ?array
    {
        if ($discount === null) {
            return null;
        }

        $offer = $account->latestRedeemedOffer();

        if ($offer === null) {
            return null;
        }

        $plans = Plan::query()->active()->with('prices')->orderBy('sort')->orderBy('name')->get();

        $prices = collect(OfferPlanPrices::for($offer, $plans))
            ->filter(fn (array $row): bool => filled($row['monthly']) || filled($row['yearly']));

        if ($prices->isEmpty()) {
            return null;
        }

        return [
            'name' => $offer->name,
            'discount_label' => OfferCopy::discountLabel($offer),
            'plans' => $prices->all(),
        ];
    }

    public function changePlan(ChangePlanRequest $request, ChangePlan $changePlan): SymfonyResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        return $changePlan->execute(
            $request->user()->account,
            Plan::active()->findOrFail($request->validated('plan_id')),
            Interval::from($request->validated('interval')),
        );
    }

    /**
     * Subscribe now on billing: a generic trial subscribes to its plan's
     * monthly price.
     */
    public function checkout(Request $request, ChangePlan $changePlan): SymfonyResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $account = $request->user()->accountOrFail();

        $plan = Plan::active()->find($account->plan_id);

        if ($account->subscribed(Account::SUBSCRIPTION_NAME) || blank($plan)) {
            return redirect()->route('app.billing.index');
        }

        return $changePlan->execute($account, $plan, Interval::Monthly);
    }

    public function portal(Request $request): RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $account = $request->user()->account;

        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        return BillingPortal::redirect($account, route('app.billing.index'));
    }
}
