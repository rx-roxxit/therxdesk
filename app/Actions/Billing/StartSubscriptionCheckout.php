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
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class StartSubscriptionCheckout
{
    public function redirect(Account $account, string $priceId, string $cancelUrl, ?Plan $plan = null, ?Offer $offer = null, CheckoutOrigin $origin = CheckoutOrigin::Welcome): Response
    {
        // A leftover Workspaces row (unpaid/canceled without `deleted`) would
        // let Processing treat auth.plan as checkout-complete and skip the wait.
        if (filled($account->plan_id) && ! $account->onGenericTrial()) {
            $account->update(['plan_id' => null]);
        }

        $account->syncOrCreateStripeCustomer();

        $subscription = $account->newSubscription(Account::SUBSCRIPTION_NAME, $priceId);

        $offerMetadata = array_filter([
            ConfigureSubscriptionCheckout::OFFER_METADATA_KEY => $offer?->id,
            ConfigureSubscriptionCheckout::OFFER_SLUG_METADATA_KEY => $offer?->slug,
        ], filled(...));

        ConfigureSubscriptionCheckout::apply($subscription, $offer, $account);

        $subscription->withMetadata($offerMetadata);

        $interval = Interval::Monthly;

        if (filled($plan)) {
            $interval = $plan->intervalFor($priceId) ?? Interval::Monthly;
        }

        $session = $subscription->checkout([
            'success_url' => route('app.billing.processing', ['from' => $origin->value]).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => array_filter([
                ConfigureSubscriptionCheckout::PLAN_METADATA_KEY => $plan?->slug,
                ConfigureSubscriptionCheckout::INTERVAL_METADATA_KEY => $interval->value,
                ConfigureSubscriptionCheckout::OFFER_SLUG_METADATA_KEY => $offer?->slug,
            ], filled(...)),
            ...ConfigureSubscriptionCheckout::sessionOptions($account->owner?->locale ?? Locale::DEFAULT),
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
