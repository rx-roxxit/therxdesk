<?php

declare(strict_types=1);

use App\Enums\Billing\CancellationType;
use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * @param  array<string, mixed>  $subscription
 * @param  list<array<string, mixed>>  $invoices
 */
function fakeReconcileStripe(array $subscription, array $invoices = []): ClientInterface
{
    $client = new class($subscription, $invoices) implements ClientInterface
    {
        /** @var list<array{method: string, url: string}> */
        public array $requests = [];

        /**
         * @param  array<string, mixed>  $subscription
         * @param  list<array<string, mixed>>  $invoices
         */
        public function __construct(public array $subscription, public array $invoices) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
        {
            $verb = strtoupper((string) $method);
            $this->requests[] = ['method' => $verb, 'url' => $absUrl];

            if (preg_match('#/v1/subscriptions/([^/?]+)#', $absUrl) === 1) {
                return [json_encode($this->subscription), 200, []];
            }

            if (str_contains($absUrl, '/v1/invoices')) {
                return [json_encode([
                    'object' => 'list',
                    'has_more' => false,
                    'data' => $this->invoices,
                ]), 200, []];
            }

            return [json_encode(['object' => 'list', 'data' => [], 'has_more' => false]), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

/**
 * @return array<string, mixed>
 */
function reconcileSubscriptionPayload(string $status = 'active'): array
{
    return [
        'id' => 'sub_reconcile',
        'object' => 'subscription',
        'status' => $status,
        'items' => ['data' => [['price' => ['id' => 'price_reconcile']]]],
        'trial_start' => now()->subDays(30)->timestamp,
        'trial_end' => now()->subDays(23)->timestamp,
        'canceled_at' => null,
        'cancel_at_period_end' => false,
        'cancellation_details' => ['reason' => null, 'feedback' => null],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function reconcileInvoices(): array
{
    return [[
        'id' => 'in_reconcile',
        'object' => 'invoice',
        'customer' => 'cus_reconcile',
        'status' => 'paid',
        'amount_paid' => 1190,
        'currency' => 'usd',
        'created' => now()->subDays(23)->timestamp,
        'status_transitions' => ['paid_at' => now()->subDays(23)->timestamp],
        'parent' => ['subscription_details' => ['subscription' => 'sub_reconcile']],
    ]];
}

function reconcileAccount(): Account
{
    $account = Account::factory()->create(['stripe_id' => 'cus_reconcile']);

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_reconcile',
        'stripe_status' => 'active',
        'stripe_price' => 'price_reconcile',
    ]);

    return $account->fresh();
}

beforeEach(function () {
    config(['trypost.self_hosted' => false, 'cashier.secret' => 'sk_test_fake']);

    $this->plan = Plan::where('slug', 'mini')->firstOrFail();
    givePlanPrice($this->plan, Interval::Monthly, 'price_reconcile');
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('it repairs the plan, trial dates and paid facts a lost event left out', function () {
    $account = reconcileAccount();
    fakeReconcileStripe(reconcileSubscriptionPayload(), reconcileInvoices());

    expect(Artisan::call('rxdesk:billing-reconcile', ['--account' => $account->id]))->toBe(0)
        ->and(Artisan::output())->toContain('1 Accounts repaired');

    $account = $account->fresh();
    $subscription = Subscription::query()->where('stripe_id', 'sub_reconcile')->firstOrFail();

    expect($account->plan_id)->toBe($this->plan->id)
        ->and($account->first_invoice_id)->toBe('in_reconcile')
        ->and($account->first_paid_at)->not->toBeNull()
        ->and($subscription->trial_started_at)->not->toBeNull()
        ->and($subscription->trial_ended_at)->not->toBeNull();
});

test('a second run finds nothing to repair', function () {
    $account = reconcileAccount();
    fakeReconcileStripe(reconcileSubscriptionPayload(), reconcileInvoices());
    Artisan::call('rxdesk:billing-reconcile', ['--account' => $account->id]);

    fakeReconcileStripe(reconcileSubscriptionPayload(), reconcileInvoices());

    expect(Artisan::call('rxdesk:billing-reconcile', ['--account' => $account->id]))->toBe(0)
        ->and(Artisan::output())->toContain('0 Accounts repaired');
});

test('a dry run reports the repair without saving it', function () {
    $account = reconcileAccount();
    fakeReconcileStripe(reconcileSubscriptionPayload(), reconcileInvoices());

    expect(Artisan::call('rxdesk:billing-reconcile', ['--account' => $account->id, '--dry-run' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('Would repair');

    $account = $account->fresh();

    expect($account->plan_id)->toBeNull()
        ->and($account->first_paid_at)->toBeNull()
        ->and(Subscription::query()->where('stripe_id', 'sub_reconcile')->firstOrFail()->trial_started_at)->toBeNull();
});

test('a cancellation Stripe knows about stops access and clears the plan', function () {
    $account = reconcileAccount();
    $account->update(['plan_id' => $this->plan->id]);

    $canceledAt = now()->subHour()->timestamp;

    fakeReconcileStripe([
        ...reconcileSubscriptionPayload('canceled'),
        'canceled_at' => $canceledAt,
        'cancellation_details' => ['reason' => 'payment_failed', 'feedback' => null],
    ]);

    expect(Artisan::call('rxdesk:billing-reconcile', ['--account' => $account->id]))->toBe(0);

    $subscription = Subscription::query()->where('stripe_id', 'sub_reconcile')->firstOrFail();

    expect($subscription->stripe_status)->toBe('canceled')
        ->and($subscription->ends_at?->timestamp)->toBe($canceledAt)
        ->and($subscription->cancellation_type)->toBe(CancellationType::PaymentFailed)
        ->and($account->fresh()->plan_id)->toBeNull();
});
