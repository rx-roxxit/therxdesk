<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\Media;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Stripe\ApiRequestor;
use Stripe\Exception\InvalidRequestException;
use Stripe\HttpClient\ClientInterface;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
});

test('customers paginate with the configured page size', function () {
    User::factory()->count(30)->create();

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index'));

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonPath('meta.total', 30)
        ->assertJsonCount(25, 'data');
});

test('search matches an owner email', function () {
    User::factory()->create(['email' => 'ann@example.com']);
    User::factory()->create(['email' => 'bob@example.com']);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index', ['search' => 'ann@']));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.owner.email', 'ann@example.com');
});

test('search matches a member email in the same account', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    User::factory()->create([
        'email' => 'member@example.com',
        'account_id' => $owner->account_id,
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index', ['search' => 'member@']));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.owner.email', 'owner@example.com');
});

test('an account without an owner is not a customer', function () {
    User::factory()->create();
    Account::factory()->create(['owner_id' => null]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index'));

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('the list carries the subscription status', function () {
    $owner = User::factory()->create();
    $owner->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'ends_at' => now()->addDay(),
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index'));

    $response->assertOk()
        ->assertJsonPath('data.0.subscription_status', 'canceled');
});

test('customer detail returns the owner, plan, Subscription, usage, members and recent posts', function () {
    $owner = User::factory()->create(['email' => 'ann@example.com']);
    $account = $owner->account;

    $plan = Plan::where('slug', 'standard')->firstOrFail();
    $monthly = $plan->priceFor(Interval::Monthly);
    $monthly->update(['stripe_price_id' => 'price_standard_monthly']);

    $account->forceFill([
        'plan_id' => $plan->id,
        'quota_bytes' => 1048576,
    ])->save();

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_standard_monthly',
        'trial_ends_at' => now()->addDays(3),
    ]);

    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    Media::factory()->temporaryUpload($workspace)->create(['size' => 2048]);

    User::factory()->count(2)->create(['account_id' => $account->id]);

    $posts = collect();

    for ($i = 0; $i < 12; $i++) {
        $posts->push(Post::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'content' => 'private post content',
            'created_at' => now()->subMinutes(12 - $i),
        ]));
    }

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id));

    $response->assertOk()
        ->assertJsonPath('owner.email', 'ann@example.com')
        ->assertJsonPath('plan.slug', 'standard')
        ->assertJsonPath('plan.quota_bytes', 5368709120)
        ->assertJsonPath('plan.ai_credits_per_month', 10000)
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('subscription.interval', 'monthly')
        ->assertJsonPath('subscription.amount', 1790)
        ->assertJsonPath('storage.used_bytes', 2048)
        ->assertJsonPath('storage.quota_bytes', 1048576)
        ->assertJsonPath('members_count', 3);

    expect($response->json('access_state'))->toBe([
        'has_app_access' => true,
        'lapsed' => false,
        'never_paid' => false,
        'suspended' => false,
        'suspended_at' => null,
        'suspension_reason' => null,
        'deletion_scheduled_at' => null,
        'data_purged_at' => null,
    ])->and($response->json('lifecycle_emails'))->toBe([]);

    $recent = $response->json('recent_posts');

    expect($recent)->toHaveCount(10)
        ->and($recent[0]['id'])->toBe($posts->last()->id)
        ->and($recent[0])->not->toHaveKey('content')
        ->and($recent[0])->not->toHaveKey('error_context');

    $returned = collect($recent)->pluck('id');

    expect($returned)->not->toContain($posts->first()->id)
        ->and($returned)->toContain($posts->last()->id);
});

test('an unknown customer is 404', function () {
    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', Str::uuid()))
        ->assertNotFound();
});

test('customer detail carries no Complimentary block before one is given', function () {
    $owner = User::factory()->create();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $owner->account_id))
        ->assertOk()
        ->assertJsonPath('complimentary', null);
});

test('customer detail carries the Complimentary block while one is in force', function () {
    $owner = User::factory()->create();
    $account = $owner->account;
    $this->travelTo(now()->startOfSecond());

    $account->forceFill([
        'plan_id' => Plan::where('slug', 'standard')->firstOrFail()->id,
        'complimentary_since' => now(),
        'complimentary_until' => now()->addDays(30),
    ])->save();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id))
        ->assertOk()
        ->assertJsonPath('complimentary.in_force', true)
        ->assertJsonPath('complimentary.since', now()->toIso8601String())
        ->assertJsonPath('complimentary.until', now()->addDays(30)->toIso8601String());
});

/**
 * Answers the Stripe reads the customer detail makes: the customer's balance,
 * the paid invoices, and one invoice with its payments expanded. Also answers
 * the refund endpoint's cancel and refund, so one fake serves both reads.
 */
function fakeCustomerDetailStripe(int $paidAt, int $amountPaid = 1190, bool $withInvoice = true, bool $withPayment = true, int $balance = 0): ClientInterface
{
    config(['cashier.secret' => 'sk_test_fake']);

    $client = new class($paidAt, $amountPaid, $withInvoice, $withPayment, $balance) implements ClientInterface
    {
        public function __construct(
            public int $paidAt,
            public int $amountPaid,
            public bool $withInvoice,
            public bool $withPayment,
            public int $balance,
        ) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
        {
            $verb = strtoupper((string) $method);

            if ($verb === 'GET' && preg_match('#/v1/customers/([^/?]+)#', $absUrl, $matches) === 1) {
                return [json_encode([
                    'id' => $matches[1],
                    'object' => 'customer',
                    'balance' => $this->balance,
                ]), 200, []];
            }

            if ($verb === 'GET' && preg_match('#/v1/invoices/(in_[^/?]+)#', $absUrl, $matches) === 1) {
                return [json_encode([
                    'id' => $matches[1],
                    'object' => 'invoice',
                    'customer' => 'cus_detail',
                    'status' => 'paid',
                    'amount_paid' => $this->amountPaid,
                    'currency' => 'usd',
                    'created' => $this->paidAt,
                    'status_transitions' => ['paid_at' => $this->paidAt],
                    'payments' => [
                        'object' => 'list',
                        'has_more' => false,
                        'data' => $this->withPayment ? [[
                            'id' => 'inpay_detail',
                            'object' => 'invoice_payment',
                            'amount_paid' => $this->amountPaid,
                            'payment' => ['payment_intent' => 'pi_detail', 'type' => 'payment_intent'],
                        ]] : [],
                    ],
                ]), 200, []];
            }

            if ($verb === 'GET' && str_contains($absUrl, '/v1/invoices')) {
                return [json_encode([
                    'object' => 'list',
                    'has_more' => false,
                    'data' => $this->withInvoice ? [[
                        'id' => 'in_detail',
                        'object' => 'invoice',
                        'customer' => 'cus_detail',
                        'status' => 'paid',
                        'amount_paid' => $this->amountPaid,
                        'currency' => 'usd',
                        'created' => $this->paidAt,
                        'status_transitions' => ['paid_at' => $this->paidAt],
                    ]] : [],
                ]), 200, []];
            }

            if ($verb === 'GET' && str_contains($absUrl, '/v1/subscriptions/')) {
                return [json_encode([
                    'id' => 'sub_detail',
                    'object' => 'subscription',
                    'status' => 'active',
                ]), 200, []];
            }

            if ($verb === 'DELETE' && str_contains($absUrl, '/v1/subscriptions/')) {
                return [json_encode([
                    'id' => 'sub_detail',
                    'object' => 'subscription',
                    'status' => 'canceled',
                ]), 200, []];
            }

            if ($verb === 'POST' && str_contains($absUrl, '/v1/refunds')) {
                return [json_encode([
                    'id' => 're_detail',
                    'object' => 'refund',
                    'status' => 'succeeded',
                    'amount' => $this->amountPaid,
                    'currency' => 'usd',
                ]), 200, []];
            }

            return [json_encode(['object' => 'list', 'data' => [], 'has_more' => false]), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

function fakeCustomerDetailFailingStripe(): ClientInterface
{
    config(['cashier.secret' => 'sk_test_fake']);

    $client = new class implements ClientInterface
    {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
        {
            throw InvalidRequestException::factory('Stripe is down.');
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

/**
 * The Account the customer-detail billing tests read: standard monthly price,
 * a Thai Stripe customer, a Subscription and an explicit Quota override.
 */
function customerDetailRefundAccount(): Account
{
    $plan = Plan::where('slug', 'standard')->firstOrFail();
    $plan->priceFor(Interval::Monthly)->update(['stripe_price_id' => 'price_detail_monthly']);

    $owner = User::factory()->create(['email' => 'detail@example.com']);
    $account = $owner->account;
    $account->forceFill([
        'plan_id' => $plan->id,
        'stripe_id' => 'cus_detail',
        'billing_country' => 'TH',
        'quota_bytes' => 1048576,
    ])->save();

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_detail',
        'stripe_status' => 'active',
        'stripe_price' => 'price_detail_monthly',
    ]);

    return $account->fresh();
}

test('customer detail adds the Offer, balance, Quota override and refund facts', function () {
    $this->travelTo(now()->startOfSecond());

    $account = customerDetailRefundAccount();
    $offer = Offer::factory()->create(['slug' => 'launch', 'name' => 'Launch']);
    $account->offers()->attach($offer, ['claimed_at' => now(), 'redeemed_at' => now()]);

    $paidAt = now()->subDay()->timestamp;
    fakeCustomerDetailStripe($paidAt, balance: -1800);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id))
        ->assertOk()
        ->assertJsonPath('id', $account->id)
        ->assertJsonPath('owner.email', 'detail@example.com')
        ->assertJsonPath('plan.slug', 'standard')
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('storage.used_bytes', 0)
        ->assertJsonPath('members_count', 1)
        ->assertJsonPath('offer.id', $offer->id)
        ->assertJsonPath('offer.slug', 'launch')
        ->assertJsonPath('offer.name', 'Launch')
        ->assertJsonPath('account_balance', 1800)
        ->assertJsonPath('quota_bytes', 1048576)
        ->assertJsonPath('refund.allowed', true)
        ->assertJsonPath('refund.reason', null)
        ->assertJsonPath('refund.charge.invoice_id', 'in_detail')
        ->assertJsonPath('refund.charge.amount_paid', 1190)
        ->assertJsonPath('refund.charge.currency', 'usd')
        ->assertJsonPath('refund.charge.paid_at', CarbonImmutable::createFromTimestampUTC($paidAt)->toIso8601String());
});

test('customer detail leaves the Offer, balance and override empty when absent', function () {
    $owner = User::factory()->create();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $owner->account_id))
        ->assertOk()
        ->assertJsonPath('offer', null)
        ->assertJsonPath('account_balance', null)
        ->assertJsonPath('quota_bytes', null)
        ->assertJsonPath('refund.allowed', false)
        ->assertJsonPath('refund.reason', 'not_thai')
        ->assertJsonPath('refund.charge', null);
});

test('customer detail refuses the refund for a non-Thai buyer', function () {
    $owner = User::factory()->create();
    $owner->account->forceFill(['billing_country' => 'US'])->save();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $owner->account_id))
        ->assertOk()
        ->assertJsonPath('refund.allowed', false)
        ->assertJsonPath('refund.reason', 'not_thai')
        ->assertJsonPath('refund.charge', null);
});

test('customer detail refuses the refund without a Subscription', function () {
    $owner = User::factory()->create();
    $owner->account->forceFill(['billing_country' => 'TH'])->save();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $owner->account_id))
        ->assertOk()
        ->assertJsonPath('refund.allowed', false)
        ->assertJsonPath('refund.reason', 'no_subscription')
        ->assertJsonPath('refund.charge', null);
});

test('customer detail refuses the refund without a Stripe customer', function () {
    $owner = User::factory()->create();
    $account = $owner->account;
    $account->forceFill(['billing_country' => 'TH'])->save();
    subscribeAccount($account);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id))
        ->assertOk()
        ->assertJsonPath('refund.allowed', false)
        ->assertJsonPath('refund.reason', 'no_stripe_customer')
        ->assertJsonPath('refund.charge', null);
});

test('customer detail refuses the refund without a paid charge in the window', function () {
    $account = customerDetailRefundAccount();
    fakeCustomerDetailStripe(now()->subDays(8)->timestamp);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id))
        ->assertOk()
        ->assertJsonPath('account_balance', null)
        ->assertJsonPath('refund.allowed', false)
        ->assertJsonPath('refund.reason', 'no_recent_charge')
        ->assertJsonPath('refund.charge', null);
});

test('customer detail refuses the refund when the charge has no refundable payment', function () {
    $account = customerDetailRefundAccount();
    fakeCustomerDetailStripe(now()->subDay()->timestamp, withPayment: false);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id))
        ->assertOk()
        ->assertJsonPath('refund.allowed', false)
        ->assertJsonPath('refund.reason', 'no_refundable_payment')
        ->assertJsonPath('refund.charge', null);
});

test('customer detail reports stripe_unavailable when Stripe fails', function () {
    $account = customerDetailRefundAccount();
    fakeCustomerDetailFailingStripe();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id))
        ->assertOk()
        ->assertJsonPath('account_balance', null)
        ->assertJsonPath('refund.allowed', false)
        ->assertJsonPath('refund.reason', 'stripe_unavailable')
        ->assertJsonPath('refund.charge', null);
});

test('customer detail allows exactly the refund the endpoint accepts', function () {
    $account = customerDetailRefundAccount();
    fakeCustomerDetailStripe(now()->subDay()->timestamp);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id))
        ->assertOk()
        ->assertJsonPath('refund.allowed', true)
        ->assertJsonPath('refund.charge.invoice_id', 'in_detail');

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.refund-and-cancel', $account->id))
        ->assertOk()
        ->assertJsonPath('invoice_id', 'in_detail')
        ->assertJsonPath('amount_paid', 1190);
});
