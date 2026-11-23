<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Support\Billing\OfferPricing;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Number;

/**
 * Three days before a Trial's first charge: the date, the amount, and how to
 * cancel. The amount is the Plan's price after the Offer, computed by
 * OfferPricing, never by the template.
 */
class TrialEnding extends LifecycleMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('rxdesk.lifecycle.trial_ending.subject'));
    }

    public function content(): Content
    {
        $recipient = $this->account->owner;
        $chargeAt = $this->account->activeTrialEndsAt();

        return new Content(
            view: 'mail.lifecycle-trial-ending',
            with: [
                'title' => __('rxdesk.lifecycle.trial_ending.title'),
                'previewText' => __('rxdesk.lifecycle.trial_ending.preview'),
                'chargeDate' => $recipient !== null ? $this->localDate($chargeAt, $recipient) : null,
                'amount' => $this->amount(),
                'billingUrl' => route('app.billing.index'),
                'supportUrl' => $this->supportUrl(),
            ],
        );
    }

    private function amount(): string
    {
        $plan = $this->account->plan;
        $subscription = $this->account->subscription(Account::SUBSCRIPTION_NAME);
        $interval = $plan?->intervalFor($subscription?->stripe_price) ?? Interval::Monthly;
        $offer = $this->account->latestRedeemedOffer();

        $pricing = $offer !== null && $plan !== null
            ? OfferPricing::forPlan($offer, $plan, $interval)
            : null;

        $cents = $pricing['discounted'] ?? $plan?->priceFor($interval)?->amount;

        if ($cents === null) {
            return '';
        }

        return Number::currency($cents / 100, 'USD', app()->getLocale());
    }
}
