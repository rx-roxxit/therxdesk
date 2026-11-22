<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\CheckoutOrigin;
use App\Enums\Billing\Interval;
use App\Enums\PostHog\CheckoutEvent;
use App\Enums\User\Locale;
use App\Jobs\PostHog\CaptureAccountEvent;
use App\Models\Account;
use App\Models\Offer;
use App\Models\Plan;
use App\Services\PostHogService;
use App\Support\Billing\ConfigureSubscriptionCheckout;
use App\Support\Billing\OpenCheckoutSessions;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class StartSubscriptionCheckout
{
    public function redirect(Account $account, string $priceId, string $cancelUrl, ?Plan $plan = null, ?Offer $offer = null, CheckoutOrigin $origin = CheckoutOrigin::Welcome): Response
    {
        // A Complimentary Plan replaces Checkout: the System admin ends it
        // before the Account can subscribe (ADR 0006).
        if ($account->isComplimentary()) {
            return redirect()->route('app.billing.index')
                ->with('flash.error', __('rxdesk.complimentary.checkout_blocked'));
        }

        // A leftover Workspaces row (unpaid/canceled without `deleted`) would
        // let Processing treat auth.plan as checkout-complete and skip the wait.
        if (filled($account->plan_id) && ! $account->onGenericTrial()) {
            $account->update(['plan_id' => null]);
        }

        $account->syncOrCreateStripeCustomer();

        OpenCheckoutSessions::expire($account);

        $subscription = $account->newSubscription(Account::SUBSCRIPTION_NAME, $priceId);

        $interval = Interval::Monthly;

        if (filled($plan)) {
            $interval = $plan->intervalFor($priceId) ?? Interval::Monthly;
        }

        // One Offer per checkout, and only when it covers the Plan and
        // interval being bought. A leftover offer from another Plan gives
        // neither its coupon nor its trial.
        if (filled($offer) && (blank($plan) || ! $offer->covers($plan, $interval))) {
            $offer = null;
        }

        $offerMetadata = array_filter([
            ConfigureSubscriptionCheckout::OFFER_METADATA_KEY => $offer?->id,
            ConfigureSubscriptionCheckout::OFFER_SLUG_METADATA_KEY => $offer?->slug,
        ], filled(...));

        ConfigureSubscriptionCheckout::apply($subscription, $offer, $account);

        $subscription->withMetadata($offerMetadata);

        $session = $subscription->checkout([
            'success_url' => route('app.billing.processing', ['from' => $origin->value]).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => array_filter([
                ConfigureSubscriptionCheckout::PLAN_METADATA_KEY => $plan?->slug,
                ConfigureSubscriptionCheckout::INTERVAL_METADATA_KEY => $interval->value,
                ConfigureSubscriptionCheckout::OFFER_SLUG_METADATA_KEY => $offer?->slug,
            ], filled(...)),
            ...ConfigureSubscriptionCheckout::sessionOptions(
                $account->owner?->locale ?? Locale::DEFAULT,
                $offer?->hasCoupon() ?? false,
            ),
        ]);

        rescue(function () use ($account, $plan, $interval, $offer): void {
            CaptureAccountEvent::dispatchIf(
                PostHogService::isEnabled() && filled($account->owner_id),
                (string) $account->owner_id,
                (string) $account->id,
                CheckoutEvent::Started,
                [
                    'plan_name' => $plan?->name,
                    'interval' => $interval->value,
                    'offer' => $offer?->slug,
                ],
            );
        });

        return Inertia::location($session->url);
    }
}
