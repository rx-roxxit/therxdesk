<?php

declare(strict_types=1);

use App\Enums\Billing\CancellationType;
use App\Enums\Billing\Interval;
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
        public function __construct(private array $failures) {}

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

    $subscription = $subscription->fresh();

    expect($subscription->paid_without_trial_at)->not->toBeNull()
        ->and($account->fresh()->first_paid_at)->not->toBeNull();
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

test('a trial whose card cannot be ended reverts the skip facts', function () {
    $account = trialAccount();
    fakeTrialWebhookStripe(['/v1/subscriptions/' => 'Stripe is down']);

    TrialCard::query()->create(['fingerprint' => 'fp_repeat', 'account_id' => $account->id]);

    (new StripeEventListener)->handle(new WebhookHandled([
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
    ]));

    $subscription = Subscription::query()->where('stripe_id', 'sub_repeat')->firstOrFail();

    expect($subscription->trial_skipped_at)->toBeNull()
        ->and($subscription->trial_ended_at)->toBeNull();
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
