<?php

declare(strict_types=1);

use App\Listeners\StripeCheckoutSessionListener;
use App\Models\Account;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Events\WebhookReceived;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiErrorException;
use Stripe\HttpClient\ClientInterface;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function blockedCheckoutPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'evt_checkout_blocked',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_blocked',
            'object' => 'checkout.session',
            'mode' => 'subscription',
            'customer' => 'cus_blocked',
            'subscription' => 'sub_blocked',
            'payment_status' => 'unpaid',
            'customer_details' => ['address' => ['country' => 'DE']],
        ]],
    ], $overrides);
}

function fakeCheckoutStripe(string $subscriptionStatus = 'trialing'): ClientInterface
{
    $client = new class($subscriptionStatus) implements ClientInterface
    {
        /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
        public array $requests = [];

        public bool $failCancel = false;

        public function __construct(public string $subscriptionStatus) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
        {
            $verb = strtoupper((string) $method);
            $this->requests[] = ['method' => $verb, 'url' => $absUrl, 'params' => $params];

            if ($verb === 'DELETE' && $this->failCancel) {
                return [json_encode(['error' => ['type' => 'api_error', 'message' => 'Stripe is down']]), 500, []];
            }

            if ($verb === 'DELETE') {
                return [json_encode(['id' => 'sub_blocked', 'object' => 'subscription', 'status' => 'canceled']), 200, []];
            }

            return [json_encode([
                'id' => 'sub_blocked',
                'object' => 'subscription',
                'status' => $this->subscriptionStatus,
            ]), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

function blockedCheckoutAccount(string $country = 'DE'): Account
{
    $account = Account::factory()->create(['stripe_id' => 'cus_blocked']);
    $owner = User::factory()->create(['account_id' => $account->id, 'country' => $country]);
    $account->update(['owner_id' => $owner->id]);

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_blocked',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_x',
        'trial_ends_at' => now()->addDays(7),
    ]);

    return $account;
}

beforeEach(function () {
    config(['trypost.self_hosted' => false, 'cashier.secret' => 'sk_test_fake']);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('a completed checkout billed to a blocked country records the facts and cancels at once', function () {
    $account = blockedCheckoutAccount();
    $stripe = fakeCheckoutStripe();

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(blockedCheckoutPayload()));

    $account = $account->fresh();
    $subscription = Subscription::query()->where('stripe_id', 'sub_blocked')->firstOrFail();

    expect($account->blocked_country)->toBe('DE')
        ->and($account->blocked_country_at)->not->toBeNull()
        ->and($account->blocked_country_payment_status)->toBe('unpaid')
        ->and($subscription->stripe_status)->toBe('canceled')
        ->and($subscription->ends_at)->not->toBeNull();

    $verbs = collect($stripe->requests)->map(fn (array $request): string => $request['method'])->all();

    expect($verbs)->toBe(['GET', 'DELETE']);

    expect(collect($stripe->requests)->contains(
        fn (array $request): bool => str_contains($request['url'], '/refunds'),
    ))->toBeFalse();
});

test('an allowed billing country is a no-op', function () {
    blockedCheckoutAccount('TH');
    $stripe = fakeCheckoutStripe();

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(blockedCheckoutPayload([
        'data' => ['object' => ['customer_details' => ['address' => ['country' => 'TH']]]],
    ])));

    expect($stripe->requests)->toBe([])
        ->and(Subscription::query()->where('stripe_id', 'sub_blocked')->firstOrFail()->stripe_status)->toBe('trialing');
});

test('a redelivered event cancels only once', function () {
    blockedCheckoutAccount();
    $stripe = fakeCheckoutStripe();
    $payload = blockedCheckoutPayload();

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived($payload));
    (new StripeCheckoutSessionListener)->handle(new WebhookReceived($payload));

    $deletes = collect($stripe->requests)->filter(
        fn (array $request): bool => $request['method'] === 'DELETE',
    );

    expect($deletes)->toHaveCount(1);
});

test('the first facts win when another blocked event arrives', function () {
    $account = blockedCheckoutAccount();
    fakeCheckoutStripe();

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(blockedCheckoutPayload([
        'id' => 'evt_first_facts',
        'data' => ['object' => ['customer_details' => ['address' => ['country' => 'DE']]]],
    ])));

    $recordedAt = $account->fresh()->blocked_country_at;

    $this->travel(1)->minutes();

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(blockedCheckoutPayload([
        'id' => 'evt_second_facts',
        'data' => ['object' => [
            'customer_details' => ['address' => ['country' => 'GB']],
            'payment_status' => 'paid',
        ]],
    ])));

    $account = $account->fresh();

    expect($account->blocked_country)->toBe('DE')
        ->and($account->blocked_country_payment_status)->toBe('unpaid')
        ->and($account->blocked_country_at->equalTo($recordedAt))->toBeTrue();
});

test('a subscription already cancelled at Stripe is not cancelled again', function () {
    blockedCheckoutAccount();
    $stripe = fakeCheckoutStripe('canceled');

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(blockedCheckoutPayload()));

    expect(collect($stripe->requests)->every(
        fn (array $request): bool => $request['method'] === 'GET',
    ))->toBeTrue()
        ->and(Subscription::query()->where('stripe_id', 'sub_blocked')->firstOrFail()->ends_at)->not->toBeNull();
});

test('a non-subscription checkout is ignored', function () {
    blockedCheckoutAccount();
    $stripe = fakeCheckoutStripe();

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(blockedCheckoutPayload([
        'data' => ['object' => ['mode' => 'payment']],
    ])));

    expect($stripe->requests)->toBe([]);
});

test('a self-hosted install ignores the completed checkout', function () {
    config(['trypost.self_hosted' => true]);

    $account = blockedCheckoutAccount();
    $stripe = fakeCheckoutStripe();

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived(blockedCheckoutPayload()));

    expect($stripe->requests)->toBe([])
        ->and($account->fresh()->blocked_country_at)->toBeNull();
});

test('a failed cancel releases the dedupe and rethrows so Stripe redelivers', function () {
    $account = blockedCheckoutAccount();
    $stripe = fakeCheckoutStripe();
    $stripe->failCancel = true;

    $payload = blockedCheckoutPayload();

    expect(fn () => (new StripeCheckoutSessionListener)->handle(new WebhookReceived($payload)))
        ->toThrow(ApiErrorException::class);

    expect(Cache::has('stripe_checkout_session:evt_checkout_blocked'))->toBeFalse()
        ->and(Subscription::query()->where('stripe_id', 'sub_blocked')->firstOrFail()->stripe_status)->toBe('trialing');

    $stripe->failCancel = false;

    (new StripeCheckoutSessionListener)->handle(new WebhookReceived($payload));

    expect(Subscription::query()->where('stripe_id', 'sub_blocked')->firstOrFail()->stripe_status)->toBe('canceled');
});
