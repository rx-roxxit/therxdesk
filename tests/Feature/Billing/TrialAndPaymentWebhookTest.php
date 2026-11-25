<?php

declare(strict_types=1);

use App\Enums\Billing\CancellationType;
use App\Enums\Billing\Interval;
use App\Exceptions\Billing\TrialEndFailed;
use App\Jobs\PostHog\TrackTrialStarted;
use App\Listeners\StripeEventListener;
use App\Listeners\StripeInvoiceListener;
use App\Models\Account;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TrialCard;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

function fakeTrialWebhookStripe(array $failures = []): ClientInterface
{
    $client = new class($failures) implements ClientInterface
    {
        /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
        public array $requests = [];

        /**
         * @param  array<string, string>  $failures
         */
        public function __construct(public array $failures) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
        {
            $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

            foreach ($this->failures as $needle => $message) {
                if (str_contains($absUrl, (string) $needle)) {
                    return [json_encode(['error' => ['type' => 'api_error', 'message' => $message]]), 500, []];
                }
            }

            if (preg_match('#/v1/payment_methods/([^/?]+)#', $absUrl, $matches) === 1) {
                return [json_encode([
                    'id' => $matches[1],
                    'object' => 'payment_method',
                    'type' => 'card',
                    'card' => ['fingerprint' => "fp_{$matches[1]}"],
                ]), 200, []];
            }

            if (str_contains($absUrl, '/v1/payment_methods')) {
                return [json_encode(['object' => 'list', 'data' => [], 'has_more' => false]), 200, []];
            }

            if (strtoupper((string) $method) === 'GET' && str_contains($absUrl, '/v1/customers/')) {
                return [json_encode([
                    'id' => 'cus_trial',
                    'object' => 'customer',
                    'invoice_settings' => ['default_payment_method' => null],
                ]), 200, []];
            }

            if (str_contains($absUrl, '/v1/subscriptions/')) {
                return [json_encode(['id' => 'sub_trial', 'object' => 'subscription']), 200, []];
            }

            return [json_encode(['object' => 'list', 'data' => [], 'has_more' => false]), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

function trialAccount(): Account
{
    $account = Account::factory()->create([
        'stripe_id' => 'cus_trial',
        'trial_ends_at' => null,
    ]);
    $user = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $user->id]);

    return $account;
}

beforeEach(function () {
    config(['trypost.self_hosted' => false, 'cashier.secret' => 'sk_test_fake']);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('a repeat card ends the trial at creation and its first charge is marked paid without trial', function () {
    enablePostHog();
    Bus::fake([TrackTrialStarted::class]);

    $account = trialAccount();
    $stripe = fakeTrialWebhookStripe();

    TrialCard::query()->create(['fingerprint' => 'fp_repeat', 'account_id' => $account->id]);

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_repeat_created',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_repeat',
            'customer' => 'cus_trial',
            'status' => 'trialing',
            'trial_start' => now()->timestamp,
            'default_payment_method' => [
                'id' => 'pm_repeat',
                'object' => 'payment_method',
                'card' => ['fingerprint' => 'fp_repeat'],
            ],
            'items' => ['data' => [['price' => ['id' => 'price_x']]]],
        ]],
    ]));

    $subscription = Subscription::query()->where('stripe_id', 'sub_repeat')->firstOrFail();

    expect($subscription->trial_started_at)->not->toBeNull()
        ->and($subscription->trial_skipped_at)->not->toBeNull()
        ->and($subscription->trial_ended_at)->not->toBeNull();

    $update = collect($stripe->requests)->first(
        fn (array $request): bool => strtoupper((string) $request['method']) === 'POST'
            && str_contains($request['url'], '/v1/subscriptions/sub_repeat')
    );

    expect($update)->not->toBeNull()
        ->and(data_get($update, 'params.trial_end'))->toBe('now');

    Bus::assertNotDispatched(TrackTrialStarted::class);

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_repeat_active',
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => ['id' => 'sub_repeat', 'customer' => 'cus_trial', 'status' => 'active'],
            'previous_attributes' => ['status' => 'trialing'],
        ],
    ]));

    // Stripe turns the subscription `active` before it attempts the charge;
    // only the paid invoice proves the repeat card was charged.
    $subscription = $subscription->fresh();

    expect($subscription->paid_without_trial_at)->toBeNull()
        ->and($account->fresh()->first_paid_at)->toBeNull();

    (new StripeInvoiceListener)->handle(new WebhookReceived([
        'id' => 'evt_repeat_invoice',
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => [
            'id' => 'in_repeat',
            'customer' => 'cus_trial',
            'subscription' => 'sub_repeat',
            'status' => 'paid',
            'amount_paid' => 1190,
        ]],
    ]));

    expect($subscription->fresh()->paid_without_trial_at)->not->toBeNull()
        ->and($account->fresh()->first_paid_at)->not->toBeNull()
        ->and($account->fresh()->first_invoice_id)->toBe('in_repeat');
});

test('a first-ever card keeps its trial and is claimed', function () {
    $account = trialAccount();
    $stripe = fakeTrialWebhookStripe();

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_first_created',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_first',
            'customer' => 'cus_trial',
            'status' => 'trialing',
            'trial_start' => now()->timestamp,
            'default_payment_method' => 'pm_first',
            'items' => ['data' => [['price' => ['id' => 'price_x']]]],
        ]],
    ]));

    $subscription = Subscription::query()->where('stripe_id', 'sub_first')->firstOrFail();

    expect($subscription->trial_started_at)->not->toBeNull()
        ->and($subscription->trial_skipped_at)->toBeNull()
        ->and($subscription->trial_ended_at)->toBeNull()
        ->and(TrialCard::query()->whereKey('fp_pm_first')->exists())->toBeTrue();

    expect(collect($stripe->requests)->contains(
        fn (array $request): bool => strtoupper((string) $request['method']) === 'POST'
            && str_contains($request['url'], '/v1/subscriptions/')
    ))->toBeFalse();
});

test('a trial-end call that fails answers non-2xx, keeps the facts and is retried', function () {
    $account = trialAccount();
    $stripe = fakeTrialWebhookStripe(['/v1/subscriptions/' => 'Stripe is down']);

    TrialCard::query()->create(['fingerprint' => 'fp_repeat', 'account_id' => $account->id]);

    $payload = [
        'id' => 'evt_repeat_failed',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_repeat',
            'customer' => 'cus_trial',
            'status' => 'trialing',
            'default_payment_method' => [
                'id' => 'pm_repeat',
                'object' => 'payment_method',
                'card' => ['fingerprint' => 'fp_repeat'],
            ],
        ]],
    ];

    expect(fn () => (new StripeEventListener)->handle(new WebhookHandled($payload)))
        ->toThrow(TrialEndFailed::class);

    $subscription = Subscription::query()->where('stripe_id', 'sub_repeat')->firstOrFail();

    expect($subscription->trial_skipped_at)->not->toBeNull()
        ->and($subscription->trial_ended_at)->not->toBeNull()
        ->and(Cache::has('stripe_webhook_event:evt_repeat_failed'))->toBeFalse();

    $stripe->failures = [];

    (new StripeEventListener)->handle(new WebhookHandled($payload));

    $endAttempts = collect($stripe->requests)->filter(
        fn (array $request): bool => strtoupper((string) $request['method']) === 'POST'
            && str_contains($request['url'], '/v1/subscriptions/sub_repeat'),
    );

    expect($endAttempts)->toHaveCount(2)
        ->and($subscription->fresh()->trial_skipped_at)->not->toBeNull();
});

test('a past_due subscription that never paid has no app access and waits for its first charge', function () {
    $account = trialAccount();
    fakeTrialWebhookStripe();

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_past_due_unpaid',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => ['id' => 'sub_past_due', 'customer' => 'cus_trial', 'status' => 'past_due']],
    ]));

    $account = $account->fresh();

    expect($account->hasAppAccess())->toBeFalse()
        ->and($account->hasPendingFirstCharge())->toBeTrue();
});

test('a past_due subscription that paid before keeps app access', function () {
    $account = trialAccount();
    $account->forceFill(['first_paid_at' => now()->subMonth()])->save();
    fakeTrialWebhookStripe();

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_past_due_paid',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => ['id' => 'sub_past_due', 'customer' => 'cus_trial', 'status' => 'past_due']],
    ]));

    $account = $account->fresh();

    expect($account->hasAppAccess())->toBeTrue()
        ->and($account->hasPendingFirstCharge())->toBeFalse();
});

test('a failed payment cancellation records its facts and leaves no plan or offer to use', function () {
    $account = trialAccount();
    $stripe = fakeTrialWebhookStripe();

    $plan = Plan::factory()->create(['slug' => 'socials', 'name' => 'Socials']);
    givePlanPrice($plan, Interval::Monthly, 'price_socials_monthly');

    $offer = Offer::factory()->create();
    $account->offers()->attach($offer, [
        'claimed_at' => now()->subDays(5),
        'redeemed_at' => now()->subDays(5),
    ]);

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_cancel_created',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_cancel',
            'customer' => 'cus_trial',
            'status' => 'trialing',
            'trial_start' => now()->subDays(8)->timestamp,
            'default_payment_method' => [
                'id' => 'pm_first',
                'object' => 'payment_method',
                'card' => ['fingerprint' => 'fp_first'],
            ],
            'items' => ['data' => [['price' => ['id' => 'price_socials_monthly']]]],
        ]],
    ]));

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_cancel_deleted',
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => [
            'id' => 'sub_cancel',
            'customer' => 'cus_trial',
            'status' => 'canceled',
            'canceled_at' => now()->subHour()->timestamp,
            'cancellation_details' => [
                'reason' => 'payment_failed',
                'feedback' => 'too_expensive',
            ],
        ]],
    ]));

    $subscription = Subscription::query()->where('stripe_id', 'sub_cancel')->firstOrFail();
    $account = $account->fresh();

    expect($subscription->cancellation_type)->toBe(CancellationType::PaymentFailed)
        ->and($subscription->cancellation_reason)->toBe('too_expensive')
        ->and($subscription->cancelled_at)->not->toBeNull()
        ->and($subscription->trial_ended_at)->not->toBeNull()
        ->and($account->plan_id)->toBeNull()
        ->and($account->canStartTrial())->toBeFalse()
        ->and($account->currentOffer())->toBeNull();

    expect(collect($stripe->requests)->contains(
        fn (array $request): bool => str_contains($request['url'], '/v1/subscriptions/')
    ))->toBeFalse();
});

test('a portal cancellation records its reason and a later webhook never overwrites the admin type', function () {
    $account = trialAccount();
    fakeTrialWebhookStripe();

    $object = [
        'id' => 'sub_voluntary',
        'customer' => 'cus_trial',
        'status' => 'active',
        'cancel_at_period_end' => true,
        'cancellation_details' => [
            'reason' => 'cancellation_requested',
            'feedback' => 'too_expensive',
        ],
    ];

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_voluntary_first',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => $object],
    ]));

    $subscription = Subscription::query()->where('stripe_id', 'sub_voluntary')->firstOrFail();

    expect($subscription->cancellation_type)->toBe(CancellationType::Voluntary)
        ->and($subscription->cancellation_reason)->toBe('too_expensive')
        ->and($subscription->cancelled_at)->not->toBeNull();

    $subscription->recordCancellation(CancellationType::Admin, 'ended by the System admin', now());

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_voluntary_second',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => $object],
    ]));

    expect($subscription->fresh()->cancellation_type)->toBe(CancellationType::Admin);
});

test('resuming before the period ends clears the cancellation facts', function () {
    $account = trialAccount();
    fakeTrialWebhookStripe();

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_resume_cancelled',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => [
            'id' => 'sub_resume',
            'customer' => 'cus_trial',
            'status' => 'active',
            'cancel_at_period_end' => true,
            'cancellation_details' => [
                'reason' => 'cancellation_requested',
                'feedback' => 'too_expensive',
            ],
        ]],
    ]));

    $subscription = Subscription::query()->where('stripe_id', 'sub_resume')->firstOrFail();

    expect($subscription->cancellation_type)->toBe(CancellationType::Voluntary)
        ->and($subscription->cancelled_at)->not->toBeNull();

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_resume_resumed',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => [
            'id' => 'sub_resume',
            'customer' => 'cus_trial',
            'status' => 'active',
            'cancel_at_period_end' => false,
        ]],
    ]));

    $subscription = $subscription->fresh();

    expect($subscription->cancellation_type)->toBeNull()
        ->and($subscription->cancellation_reason)->toBeNull()
        ->and($subscription->cancelled_at)->toBeNull();
});

test('the first paid invoice records the first payment and is never replaced', function () {
    $account = trialAccount();

    $paidAt = now()->subHour()->startOfSecond();

    (new StripeInvoiceListener)->handle(new WebhookReceived([
        'id' => 'evt_invoice_first',
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => [
            'id' => 'in_first',
            'customer' => 'cus_trial',
            'status' => 'paid',
            'amount_paid' => 1900,
            'status_transitions' => ['paid_at' => $paidAt->timestamp],
        ]],
    ]));

    $account = $account->fresh();

    expect($account->first_invoice_id)->toBe('in_first')
        ->and($account->first_paid_at?->toIso8601String())->toBe($paidAt->toIso8601String());

    (new StripeInvoiceListener)->handle(new WebhookReceived([
        'id' => 'evt_invoice_second',
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => [
            'id' => 'in_second',
            'customer' => 'cus_trial',
            'status' => 'paid',
            'amount_paid' => 1900,
            'status_transitions' => ['paid_at' => now()->timestamp],
        ]],
    ]));

    expect($account->fresh()->first_invoice_id)->toBe('in_first');
});

test('a handled event is marked so a second delivery is skipped', function () {
    $account = trialAccount();
    $stripe = fakeTrialWebhookStripe();

    TrialCard::query()->create(['fingerprint' => 'fp_once', 'account_id' => $account->id]);

    $payload = [
        'id' => 'evt_marked_once',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_marked_once',
            'customer' => 'cus_trial',
            'status' => 'trialing',
            'default_payment_method' => [
                'id' => 'pm_once',
                'object' => 'payment_method',
                'card' => ['fingerprint' => 'fp_once'],
            ],
            'items' => ['data' => [['price' => ['id' => 'price_x']]]],
        ]],
    ];

    (new StripeEventListener)->handle(new WebhookHandled($payload));

    expect(Cache::has('stripe_webhook_event:evt_marked_once'))->toBeTrue();

    (new StripeEventListener)->handle(new WebhookHandled($payload));

    $ends = collect($stripe->requests)->filter(
        fn (array $request): bool => strtoupper((string) $request['method']) === 'POST'
            && str_contains($request['url'], '/v1/subscriptions/sub_marked_once'),
    );

    expect($ends)->toHaveCount(1);
});

test('a repeat delivery of a created event never ends its own trial', function () {
    $account = trialAccount();
    $stripe = fakeTrialWebhookStripe();

    $payload = [
        'id' => 'evt_redelivered_created',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_redelivered',
            'customer' => 'cus_trial',
            'status' => 'trialing',
            'trial_start' => now()->timestamp,
            'default_payment_method' => 'pm_redelivered',
            'items' => ['data' => [['price' => ['id' => 'price_x']]]],
        ]],
    ];

    (new StripeEventListener)->handle(new WebhookHandled($payload));

    // The event-id key lives one day; Stripe retries for three.
    Cache::forget('stripe_webhook_event:evt_redelivered_created');

    (new StripeEventListener)->handle(new WebhookHandled($payload));

    $subscription = Subscription::query()->where('stripe_id', 'sub_redelivered')->firstOrFail();

    expect($subscription->trial_skipped_at)->toBeNull()
        ->and($subscription->trial_ended_at)->toBeNull();

    expect(collect($stripe->requests)->contains(
        fn (array $request): bool => strtoupper((string) $request['method']) === 'POST'
            && str_contains($request['url'], '/v1/subscriptions/sub_redelivered'),
    ))->toBeFalse();
});

test('a second trial on one account is ended at once even with another card', function () {
    $account = trialAccount();
    $stripe = fakeTrialWebhookStripe();

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_account_first_trial',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_first_trial',
            'customer' => 'cus_trial',
            'status' => 'trialing',
            'trial_start' => now()->timestamp,
            'default_payment_method' => 'pm_first_account_card',
            'items' => ['data' => [['price' => ['id' => 'price_x']]]],
        ]],
    ]));

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_account_second_trial',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_second_trial',
            'customer' => 'cus_trial',
            'status' => 'trialing',
            'trial_start' => now()->timestamp,
            'default_payment_method' => 'pm_second_account_card',
            'items' => ['data' => [['price' => ['id' => 'price_x']]]],
        ]],
    ]));

    $first = Subscription::query()->where('stripe_id', 'sub_first_trial')->firstOrFail();
    $second = Subscription::query()->where('stripe_id', 'sub_second_trial')->firstOrFail();

    expect($first->trial_skipped_at)->toBeNull()
        ->and($first->trial_ended_at)->toBeNull()
        ->and($second->trial_skipped_at)->not->toBeNull()
        ->and($second->trial_ended_at)->not->toBeNull();

    expect(collect($stripe->requests)->contains(
        fn (array $request): bool => strtoupper((string) $request['method']) === 'POST'
            && str_contains($request['url'], '/v1/subscriptions/sub_second_trial'),
    ))->toBeTrue()
        ->and(collect($stripe->requests)->contains(
            fn (array $request): bool => strtoupper((string) $request['method']) === 'POST'
                && str_contains($request['url'], '/v1/subscriptions/sub_first_trial'),
        ))->toBeFalse();
});

test('a subscription born active with a zero first invoice records no first payment', function () {
    $account = trialAccount();
    fakeTrialWebhookStripe();

    (new StripeEventListener)->handle(new WebhookHandled([
        'id' => 'evt_zero_coupon_created',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_zero_coupon',
            'customer' => 'cus_trial',
            'status' => 'active',
            'items' => ['data' => [['price' => ['id' => 'price_x']]]],
        ]],
    ]));

    expect($account->fresh()->first_paid_at)->toBeNull();

    (new StripeInvoiceListener)->handle(new WebhookReceived([
        'id' => 'evt_zero_coupon_invoice',
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => [
            'id' => 'in_zero_coupon',
            'customer' => 'cus_trial',
            'subscription' => 'sub_zero_coupon',
            'status' => 'paid',
            'amount_paid' => 0,
        ]],
    ]));

    $account = $account->fresh();

    expect($account->first_paid_at)->toBeNull()
        ->and($account->first_invoice_id)->toBeNull()
        ->and($account->hasPaidSubscription())->toBeFalse();
});

test('a webhook that loaded the row before an admin cancellation never overwrites it', function () {
    $account = trialAccount();

    $subscription = $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_admin_race',
        'stripe_status' => 'active',
        'stripe_price' => 'price_x',
    ]);

    $stale = Subscription::query()->findOrFail($subscription->id);

    Subscription::query()->whereKey($subscription->id)->update([
        'cancellation_type' => CancellationType::Admin->value,
        'cancellation_reason' => 'ended by the System admin',
        'cancelled_at' => now(),
    ]);

    $stale->recordCancellation(CancellationType::Voluntary, 'too_expensive', now());

    expect($subscription->fresh()->cancellation_type)->toBe(CancellationType::Admin)
        ->and($subscription->fresh()->cancellation_reason)->toBe('ended by the System admin');
});

test('resuming never clears an admin cancellation', function () {
    $account = trialAccount();

    $subscription = $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_admin_resume',
        'stripe_status' => 'active',
        'stripe_price' => 'price_x',
    ]);

    Subscription::query()->whereKey($subscription->id)->update([
        'cancellation_type' => CancellationType::Voluntary->value,
        'cancelled_at' => now(),
    ]);

    $stale = Subscription::query()->findOrFail($subscription->id);

    Subscription::query()->whereKey($subscription->id)->update([
        'cancellation_type' => CancellationType::Admin->value,
        'cancellation_reason' => 'ended by the System admin',
    ]);

    $stale->clearCancellation();

    expect($subscription->fresh()->cancellation_type)->toBe(CancellationType::Admin)
        ->and($subscription->fresh()->cancelled_at)->not->toBeNull();
});

test('an invoice that paid nothing records no first payment', function () {
    $account = Account::factory()->create(['stripe_id' => 'cus_zero', 'trial_ends_at' => null]);

    (new StripeInvoiceListener)->handle(new WebhookReceived([
        'id' => 'evt_invoice_zero',
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => [
            'id' => 'in_zero',
            'customer' => 'cus_zero',
            'status' => 'paid',
            'amount_paid' => 0,
        ]],
    ]));

    $account = $account->fresh();

    expect($account->first_invoice_id)->toBeNull()
        ->and($account->first_paid_at)->toBeNull();
});
