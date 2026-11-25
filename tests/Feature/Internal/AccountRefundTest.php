<?php

declare(strict_types=1);

use App\Enums\Billing\CancellationType;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

function refundAccount(string $country = 'TH'): Account
{
    $account = Account::factory()->create([
        'stripe_id' => 'cus_thai',
        'billing_country' => $country,
    ]);
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_thai',
        'stripe_status' => 'active',
        'stripe_price' => 'price_thai',
    ]);

    return $account->fresh();
}

function fakeRefundStripe(
    int $paidAt,
    int $amountPaid = 1190,
    bool $withInvoice = true,
    int $amountRefunded = 0,
    bool $failCancel = false,
): ClientInterface {
    $client = new class($paidAt, $amountPaid, $withInvoice, $amountRefunded, $failCancel) implements ClientInterface
    {
        /** @var list<array{method: string, url: string, params: array<string, mixed>, headers: array<int, string>}> */
        public array $requests = [];

        public function __construct(
            public int $paidAt,
            public int $amountPaid,
            public bool $withInvoice,
            public int $amountRefunded,
            public bool $failCancel,
        ) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
        {
            $verb = strtoupper((string) $method);
            $this->requests[] = ['method' => $verb, 'url' => $absUrl, 'params' => $params, 'headers' => $headers];

            if ($verb === 'GET' && preg_match('#/v1/invoices/(in_[^/?]+)#', $absUrl, $matches) === 1) {
                return [json_encode([
                    'id' => $matches[1],
                    'object' => 'invoice',
                    'customer' => 'cus_thai',
                    'status' => 'paid',
                    'amount_paid' => $this->amountPaid,
                    'amount_refunded' => $this->amountRefunded,
                    'currency' => 'usd',
                    'payments' => [
                        'object' => 'list',
                        'has_more' => false,
                        'data' => [[
                            'id' => 'inpay_thai',
                            'object' => 'invoice_payment',
                            'amount_paid' => $this->amountPaid,
                            'payment' => ['payment_intent' => 'pi_thai', 'type' => 'payment_intent'],
                        ]],
                    ],
                ]), 200, []];
            }

            if ($verb === 'GET' && str_contains($absUrl, '/v1/invoices')) {
                return [json_encode([
                    'object' => 'list',
                    'has_more' => false,
                    'data' => $this->withInvoice ? [[
                        'id' => 'in_thai',
                        'object' => 'invoice',
                        'customer' => 'cus_thai',
                        'status' => 'paid',
                        'amount_paid' => $this->amountPaid,
                        'amount_refunded' => $this->amountRefunded,
                        'currency' => 'usd',
                        'payment_intent' => 'pi_thai',
                        'created' => $this->paidAt,
                        'status_transitions' => ['paid_at' => $this->paidAt],
                    ]] : [],
                ]), 200, []];
            }

            if ($verb === 'POST' && str_contains($absUrl, '/v1/refunds')) {
                $amount = (int) ($params['amount'] ?? $this->amountPaid);
                $this->amountRefunded += $amount;

                return [json_encode([
                    'id' => 're_thai',
                    'object' => 'refund',
                    'status' => 'succeeded',
                    'amount' => $amount,
                    'currency' => 'usd',
                ]), 200, []];
            }

            if ($verb === 'GET' && str_contains($absUrl, '/v1/subscriptions/')) {
                return [json_encode([
                    'id' => 'sub_thai',
                    'object' => 'subscription',
                    'status' => 'active',
                ]), 200, []];
            }

            if ($verb === 'DELETE' && str_contains($absUrl, '/v1/subscriptions/')) {
                if ($this->failCancel) {
                    return [json_encode([
                        'error' => ['type' => 'api_error', 'message' => 'Stripe cannot cancel now'],
                    ]), 500, []];
                }

                return [json_encode([
                    'id' => 'sub_thai',
                    'object' => 'subscription',
                    'status' => 'canceled',
                ]), 200, []];
            }

            return [json_encode(['object' => 'list', 'data' => [], 'has_more' => false]), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'cashier.secret' => 'sk_test_fake',
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

$refund = function (Account $account) {
    return test()->withHeaders(test()->adminHeaders)
        ->postJson(route('internal.accounts.refund-and-cancel', $account->id));
};

test('inside the window it refunds the charge, cancels at once and audits the action', function () use ($refund) {
    $account = refundAccount();
    $stripe = fakeRefundStripe(now()->subDay()->timestamp);

    $refund($account)
        ->assertOk()
        ->assertJsonPath('invoice_id', 'in_thai')
        ->assertJsonPath('amount_paid', 1190)
        ->assertJsonPath('refund_id', 're_thai')
        ->assertJsonPath('cancellation_type', 'admin')
        ->assertJsonPath('has_app_access', false);

    $subscription = Subscription::query()->where('stripe_id', 'sub_thai')->firstOrFail();

    expect($subscription->stripe_status)->toBe('canceled')
        ->and($subscription->ends_at)->not->toBeNull()
        ->and($subscription->cancellation_type)->toBe(CancellationType::Admin)
        ->and($account->fresh()->hasAppAccess())->toBeFalse();

    $refundRequest = collect($stripe->requests)->first(
        fn (array $request): bool => $request['method'] === 'POST' && str_contains($request['url'], '/refunds'),
    );

    expect($refundRequest)->not->toBeNull()
        ->and($refundRequest['params']['amount'])->toBe(1190)
        ->and($refundRequest['params']['payment_intent'])->toBe('pi_thai')
        ->and($refundRequest['headers'])->toContain('Idempotency-Key: rxdesk-refund-in_thai');

    $cancel = collect($stripe->requests)->first(
        fn (array $request): bool => $request['method'] === 'DELETE',
    );

    expect($cancel['url'])->toContain('/v1/subscriptions/sub_thai');

    $entry = AuditLog::query()->where('action', 'account.refund_and_cancel')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('accounts')
        ->and($entry->target_id)->toBe($account->id)
        ->and($entry->before['status'])->toBe('active')
        ->and($entry->after['invoice_id'])->toBe('in_thai')
        ->and($entry->after['amount_paid'])->toBe(1190)
        ->and($entry->after['currency'])->toBe('usd')
        ->and($entry->after['refund_id'])->toBe('re_thai')
        ->and($entry->after['subscription_id'])->toBe('sub_thai')
        ->and($entry->after['cancellation_type'])->toBe(CancellationType::Admin->value);

    expect(json_encode($entry->after))->not->toContain('sk_')
        ->and(json_encode($entry->after))->not->toContain('secret');
});

test('outside the seven-day window it refuses and changes nothing', function () use ($refund) {
    $account = refundAccount();
    $stripe = fakeRefundStripe(now()->subDays(8)->timestamp);

    $refund($account)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account');

    expect(collect($stripe->requests)->contains(
        fn (array $request): bool => $request['method'] === 'POST' && str_contains($request['url'], '/refunds'),
    ))->toBeFalse()
        ->and(Subscription::query()->where('stripe_id', 'sub_thai')->firstOrFail()->stripe_status)->toBe('active')
        ->and($account->fresh()->hasAppAccess())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'account.refund_and_cancel')->exists())->toBeFalse();
});

test('only the part of the charge that was not refunded yet is refunded', function () use ($refund) {
    $account = refundAccount();
    $stripe = fakeRefundStripe(now()->subDay()->timestamp, amountRefunded: 190);

    $refund($account)
        ->assertOk()
        ->assertJsonPath('amount_paid', 1000)
        ->assertJsonPath('refund_id', 're_thai');

    $refundRequest = collect($stripe->requests)->first(
        fn (array $request): bool => $request['method'] === 'POST' && str_contains($request['url'], '/refunds'),
    );

    expect($refundRequest['params']['amount'])->toBe(1000);
});

test('an already refunded charge is cancelled without a second refund', function () use ($refund) {
    $account = refundAccount();
    $stripe = fakeRefundStripe(now()->subDay()->timestamp, amountRefunded: 1190);

    $refund($account)
        ->assertOk()
        ->assertJsonPath('amount_paid', 0)
        ->assertJsonPath('refund_id', null);

    expect(collect($stripe->requests)->contains(
        fn (array $request): bool => $request['method'] === 'POST' && str_contains($request['url'], '/refunds'),
    ))->toBeFalse()
        ->and(Subscription::query()->where('stripe_id', 'sub_thai')->firstOrFail()->stripe_status)->toBe('canceled');
});

test('a refund that succeeded is not repeated when the cancel is retried', function () use ($refund) {
    $account = refundAccount();
    $stripe = fakeRefundStripe(now()->subDay()->timestamp, failCancel: true);

    $refund($account)->assertStatus(502);

    expect(Subscription::query()->where('stripe_id', 'sub_thai')->firstOrFail()->stripe_status)->toBe('active');

    $stripe->failCancel = false;

    $refund($account)
        ->assertOk()
        ->assertJsonPath('amount_paid', 0)
        ->assertJsonPath('refund_id', null);

    $refunds = collect($stripe->requests)->filter(
        fn (array $request): bool => $request['method'] === 'POST' && str_contains($request['url'], '/refunds'),
    );

    expect($refunds)->toHaveCount(1)
        ->and(Subscription::query()->where('stripe_id', 'sub_thai')->firstOrFail()->stripe_status)->toBe('canceled');
});

test('the customer detail stops offering the action once the Subscription is cancelled', function () {
    $account = refundAccount();
    fakeRefundStripe(now()->subDay()->timestamp);

    Subscription::query()->where('stripe_id', 'sub_thai')->firstOrFail()->forceFill([
        'stripe_status' => 'canceled',
        'ends_at' => now(),
    ])->save();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id))
        ->assertOk()
        ->assertJsonPath('refund.allowed', false)
        ->assertJsonPath('refund.reason', 'already_cancelled');
});

test('a non-Thai buyer is refused before Stripe is called', function () use ($refund) {
    $account = refundAccount('US');
    $stripe = fakeRefundStripe(now()->subDay()->timestamp);

    $refund($account)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account');

    expect($stripe->requests)->toBe([]);
});

test('no paid charge in the window is refused', function () use ($refund) {
    $account = refundAccount();
    $stripe = fakeRefundStripe(now()->subDay()->timestamp, withInvoice: false);

    $refund($account)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account');

    expect(collect($stripe->requests)->contains(
        fn (array $request): bool => $request['method'] === 'POST',
    ))->toBeFalse()
        ->and(Subscription::query()->where('stripe_id', 'sub_thai')->firstOrFail()->stripe_status)->toBe('active');
});

test('the refund endpoint is not reachable without the admin token', function () {
    $account = refundAccount();

    $this->postJson(route('internal.accounts.refund-and-cancel', $account->id))
        ->assertNotFound();
});

test('an unknown account is 404', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.refund-and-cancel', Str::uuid()))
        ->assertNotFound();
});

test('the site token is refused by the refund endpoint', function () {
    $account = refundAccount();

    $this->withHeaders([
        'Authorization' => 'Bearer test-site-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ])->postJson(route('internal.accounts.refund-and-cancel', $account->id))
        ->assertNotFound();
});
