<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Jobs\Billing\SendThaiPurchaseDocument;
use App\Listeners\StripeCheckoutSessionListener;
use App\Mail\ThaiPurchaseDocumentMail;
use App\Models\Account;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use App\Support\Billing\ThaiPurchaseDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Cashier\Events\WebhookReceived;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function thaiCheckoutPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'evt_thai_checkout',
        'type' => 'checkout.session.completed',
        'created' => now()->timestamp,
        'data' => ['object' => [
            'id' => 'cs_thai',
            'object' => 'checkout.session',
            'mode' => 'subscription',
            'customer' => 'cus_thai',
            'subscription' => 'sub_thai',
            'payment_status' => 'unpaid',
            'customer_details' => [
                'name' => 'Somchai Jaidee',
                'address' => ['country' => 'TH'],
            ],
        ]],
    ], $overrides);
}

function recordThaiOperator(): Operator
{
    $operator = Operator::factory()->create([
        'legal_name_th' => 'บริษัท ตัวอย่าง จำกัด',
        'address_th' => '99 ถนนสุขุมวิท กรุงเทพมหานคร 10110',
    ]);

    Operator::forgetCurrent();

    return $operator;
}

function thaiCheckoutAccount(string $country = 'TH'): Account
{
    $plan = Plan::query()->where('slug', 'mini')->firstOrFail();
    givePlanPrice($plan, Interval::Monthly, 'price_thai_monthly', 1190);

    $account = Account::factory()->create([
        'stripe_id' => 'cus_thai',
        'plan_id' => $plan->id,
        'billing_country' => $country,
    ]);
    $owner = User::factory()->create(['account_id' => $account->id, 'name' => 'Owner Name']);
    $account->update(['owner_id' => $owner->id]);

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_thai',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_thai_monthly',
        'trial_started_at' => now(),
        'trial_ends_at' => now()->addDays(7),
    ]);

    return $account->fresh();
}

function fakeThaiStripe(int $paidAt, int $amountPaid = 1190, bool $withInvoice = true, string $subscriptionStatus = 'active'): ClientInterface
{
    $client = new class($paidAt, $amountPaid, $withInvoice, $subscriptionStatus) implements ClientInterface
    {
        /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
        public array $requests = [];

        public function __construct(
            public int $paidAt,
            public int $amountPaid,
            public bool $withInvoice,
            public string $subscriptionStatus,
        ) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
        {
            $verb = strtoupper((string) $method);
            $this->requests[] = ['method' => $verb, 'url' => $absUrl, 'params' => $params];

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
                        'currency' => 'usd',
                        'payment_intent' => 'pi_thai',
                        'created' => $this->paidAt,
                        'status_transitions' => ['paid_at' => $this->paidAt],
                    ]] : [],
                ]), 200, []];
            }

            if ($verb === 'POST' && str_contains($absUrl, '/v1/refunds')) {
                return [json_encode([
                    'id' => 're_thai',
                    'object' => 'refund',
                    'status' => 'succeeded',
                    'amount' => $this->amountPaid,
                    'currency' => 'usd',
                ]), 200, []];
            }

            if ($verb === 'GET' && str_contains($absUrl, '/v1/subscriptions/')) {
                return [json_encode([
                    'id' => 'sub_thai',
                    'object' => 'subscription',
                    'status' => $this->subscriptionStatus,
                    'trial_start' => $this->subscriptionStatus === 'trialing' ? $this->paidAt : null,
                    'trial_end' => $this->subscriptionStatus === 'trialing' ? $this->paidAt + 604800 : null,
                    'items' => ['data' => [['price' => ['id' => 'price_thai_monthly']]]],
                ]), 200, []];
            }

            if ($verb === 'DELETE' && str_contains($absUrl, '/v1/subscriptions/')) {
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
    config(['trypost.self_hosted' => false, 'cashier.secret' => 'sk_test_fake']);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('a Thai checkout records its facts and queues the purchase document once per session', function () {
    Queue::fake();
    recordThaiOperator();
    $account = thaiCheckoutAccount();

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(thaiCheckoutPayload()));

    $account = $account->fresh();

    expect($account->billing_country)->toBe('TH')
        ->and($account->billing_name)->toBe('Somchai Jaidee')
        ->and($account->checkout_completed_at)->not->toBeNull();

    Queue::assertPushed(SendThaiPurchaseDocument::class, function (SendThaiPurchaseDocument $job) use ($account): bool {
        return $job->accountId === $account->id && $job->sessionId === 'cs_thai';
    });
});

test('the purchase document is sent once per checkout session', function () {
    Mail::fake();
    recordThaiOperator();
    $account = thaiCheckoutAccount();

    (new SendThaiPurchaseDocument($account->id, 'cs_thai'))->handle();
    (new SendThaiPurchaseDocument($account->id, 'cs_thai'))->handle();

    Mail::assertQueued(ThaiPurchaseDocumentMail::class, function (ThaiPurchaseDocumentMail $mail): bool {
        return $mail->document['rows'][0]['value'] === 'บริษัท ตัวอย่าง จำกัด'
            && $mail->document['rows'][5]['value'] === '$11.90 ต่อเดือน';
    });

    Mail::assertQueued(ThaiPurchaseDocumentMail::class, 1);
});

test('a checkout billed outside Thailand records its facts and queues no document', function () {
    Queue::fake();
    recordThaiOperator();
    $account = thaiCheckoutAccount('US');

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(thaiCheckoutPayload([
        'data' => ['object' => ['customer_details' => ['address' => ['country' => 'US']]]],
    ])));

    expect($account->fresh()->billing_country)->toBe('US');

    Queue::assertNothingPushed();
});

test('an older redelivery never replaces newer checkout facts', function () {
    Queue::fake();
    recordThaiOperator();
    $account = thaiCheckoutAccount();

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(thaiCheckoutPayload([
        'created' => now()->timestamp,
    ])));

    $completedAt = $account->fresh()->checkout_completed_at;

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(thaiCheckoutPayload([
        'id' => 'evt_thai_checkout_old',
        'created' => now()->subDay()->timestamp,
        'data' => ['object' => ['customer_details' => ['name' => 'Old Name', 'address' => ['country' => 'US']]]],
    ])));

    $account = $account->fresh();

    expect($account->billing_country)->toBe('TH')
        ->and($account->billing_name)->toBe('Somchai Jaidee')
        ->and($account->checkout_completed_at->equalTo($completedAt))->toBeTrue();
});

test('a blocked checkout still records the facts before it cancels', function () {
    recordThaiOperator();
    $account = thaiCheckoutAccount('TH');
    $stripe = fakeThaiStripe(now()->timestamp, subscriptionStatus: 'trialing');

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(thaiCheckoutPayload([
        'data' => ['object' => ['customer_details' => ['address' => ['country' => 'DE']]]],
    ])));

    expect($account->fresh()->billing_country)->toBe('DE');

    $verbs = collect($stripe->requests)->map(fn (array $request): string => $request['method'])->all();

    expect($verbs)->toBe(['GET', 'DELETE']);
});

test('the processing page carries the Thai document from the current operator', function () {
    recordThaiOperator();
    $account = thaiCheckoutAccount();
    $user = $account->owner;

    $this->actingAs($user)
        ->get(route('app.billing.processing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('billing/Processing', false)
            ->where('purchaseDocument.title', 'เอกสารการซื้อสินค้าและบริการ')
            ->where('purchaseDocument.rows.0.label', 'ผู้ขาย')
            ->where('purchaseDocument.rows.0.value', 'บริษัท ตัวอย่าง จำกัด')
            ->where('purchaseDocument.rows.1.value', '99 ถนนสุขุมวิท กรุงเทพมหานคร 10110')
            ->where('purchaseDocument.rows.2.label', 'ผู้ซื้อ')
            ->where('purchaseDocument.rows.2.value', 'Owner Name')
            ->where('purchaseDocument.rows.5.value', '$11.90 ต่อเดือน')
            ->where('purchaseDocument.cancellation', ThaiPurchaseDocument::CANCELLATION_TEXT)
        );

    $operator = Operator::current();
    $operator->update(['legal_name_th' => 'บริษัท ใหม่ จำกัด']);
    Operator::forgetCurrent();

    $this->actingAs($user)
        ->get(route('app.billing.processing'))
        ->assertInertia(fn ($page) => $page
            ->where('purchaseDocument.rows.0.value', 'บริษัท ใหม่ จำกัด')
        );
});

test('the processing page carries no document for a buyer outside Thailand', function () {
    recordThaiOperator();
    $account = thaiCheckoutAccount('US');
    $user = $account->owner;

    $this->actingAs($user)
        ->get(route('app.billing.processing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('purchaseDocument', null));
});

test('the processing page carries no document without a recorded operator', function () {
    $account = thaiCheckoutAccount('TH');
    $user = $account->owner;

    $this->actingAs($user)
        ->get(route('app.billing.processing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('purchaseDocument', null));
});

test('the order-confirmation email carries the same Thai document', function () {
    $document = [
        'title' => 'เอกสารการซื้อสินค้าและบริการ',
        'rows' => [
            ['label' => 'ผู้ขาย', 'value' => 'บริษัท ตัวอย่าง จำกัด'],
            ['label' => 'ผู้ซื้อ', 'value' => 'Somchai Jaidee'],
        ],
        'cancellation' => ThaiPurchaseDocument::CANCELLATION_TEXT,
    ];

    $mailable = new ThaiPurchaseDocumentMail($document);

    $mailable->assertHasSubject('เอกสารการซื้อสินค้าและบริการ – The RX Desk')
        ->assertSeeInHtml('เอกสารการซื้อสินค้าและบริการ')
        ->assertSeeInHtml('บริษัท ตัวอย่าง จำกัด')
        ->assertSeeInHtml('Somchai Jaidee')
        ->assertSeeInHtml(ThaiPurchaseDocument::CANCELLATION_TEXT);
});
