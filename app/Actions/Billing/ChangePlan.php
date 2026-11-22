<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\CheckoutOrigin;
use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\Plan;
use App\Support\Billing\BlockedCountries;
use App\Support\Billing\SubscriptionPlanSync;
use Illuminate\Support\Facades\Gate;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Symfony\Component\HttpFoundation\Response;

class ChangePlan
{
    public function __construct(private StartSubscriptionCheckout $checkout) {}

    /**
     * Moves the account to a plan's price, once `swapPlan` allows it. A
     * subscription swaps its price; an account without a subscription
     * subscribes through Stripe Checkout, charged at once, with the coupon
     * Offer it is still eligible for.
     *
     * The subscription's discount is kept while the Offer it was bought with
     * covers the new Plan and interval, and dropped otherwise. Coverage is
     * what stops a monthly percentage from being taken off a whole year.
     */
    public function execute(Account $account, Plan $plan, Interval $interval): Response
    {
        // A Complimentary Plan replaces Checkout: the System admin ends it
        // before the Account can subscribe (ADR 0006).
        if ($account->isComplimentary()) {
            return back()->with('flash.error', __('rxdesk.complimentary.checkout_blocked'));
        }

        if (BlockedCountries::isBlocked($account->owner?->country)) {
            return back()->with('flash.error', __('rxdesk.country.blocked'));
        }

        $authorization = Gate::inspect('swapPlan', [$account, $plan]);

        if ($authorization->denied()) {
            return back()->with('flash.error', $authorization->message());
        }

        $priceId = $plan->priceFor($interval)?->stripe_price_id;

        abort_if(blank($priceId), Response::HTTP_UNPROCESSABLE_ENTITY, 'No price configured for this interval');

        if (! $account->subscribed(Account::SUBSCRIPTION_NAME)) {
            return $this->checkout->redirect(
                $account,
                $priceId,
                route('app.billing.index'),
                $plan,
                $account->currentOffer(withTrial: false),
                CheckoutOrigin::Billing,
            );
        }

        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);
        $offer = $account->latestRedeemedOffer();
        $keepsDiscount = $offer?->covers($plan, $interval) ?? false;

        try {
            if ($subscription->stripe_price !== $priceId) {
                $subscription->swap($priceId, $keepsDiscount ? [] : ['discounts' => '']);
            }
        } catch (IncompletePayment $exception) {
            return redirect()->route('cashier.payment', [
                $exception->payment->id,
                'redirect' => route('app.billing.index'),
            ]);
        }

        $subscription->refresh();

        if (! SubscriptionPlanSync::allows($subscription->stripe_status)) {
            return redirect()->route('app.billing.index');
        }

        $account->update(['plan_id' => $plan->id]);

        return redirect()->route('app.billing.index')
            ->with('flash.success', __('billing.flash.plan_changed', ['plan' => $plan->name]));
    }
}
