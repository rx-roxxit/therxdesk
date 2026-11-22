<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\CatalogSetting;
use App\Models\User;
use App\Models\Workspace;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

function fakeBillingPortalStripe(array $missingConfigurations = []): ClientInterface
{
    $client = new class($missingConfigurations) implements ClientInterface
    {
        /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
        public array $requests = [];

        /**
         * @param  list<string>  $missingConfigurations
         */
        public function __construct(private array $missingConfigurations) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
        {
            $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

            if (str_contains($absUrl, '/v1/billing_portal/sessions')) {
                return [json_encode([
                    'id' => 'bps_new',
                    'object' => 'billing_portal.session',
                    'url' => 'https://billing.stripe.test/session',
                ]), 200, []];
            }

            if (preg_match('#/v1/billing_portal/configurations/([^/?]+)#', $absUrl, $matches) === 1) {
                if (in_array($matches[1], $this->missingConfigurations, true)) {
                    return [json_encode([
                        'error' => [
                            'type' => 'invalid_request_error',
                            'code' => 'resource_missing',
                            'message' => 'No such configuration',
                        ],
                    ]), 404, []];
                }

                return [json_encode(['id' => $matches[1], 'object' => 'billing_portal.configuration']), 200, []];
            }

            if (str_contains($absUrl, '/v1/billing_portal/configurations')) {
                return [json_encode(['id' => 'bpc_new', 'object' => 'billing_portal.configuration']), 200, []];
            }

            return [json_encode(['object' => 'list', 'data' => [], 'has_more' => false]), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

/**
 * The recorded requests whose URL contains the needle, optionally by method.
 *
 * @return array<int, array{method: string, url: string, params: array<string, mixed>}>
 */
function billingPortalRequests(ClientInterface $client, string $needle, ?string $method = null): array
{
    return collect($client->requests)
        ->filter(fn (array $request): bool => str_contains($request['url'], $needle)
            && ($method === null || strtoupper((string) $request['method']) === $method))
        ->values()
        ->all();
}

beforeEach(function () {
    config(['trypost.self_hosted' => false, 'cashier.secret' => 'sk_test_fake']);

    $this->account = Account::factory()->create(['stripe_id' => 'cus_portal', 'trial_ends_at' => null]);
    $this->user = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_portal',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    $workspace->members()->attach($this->user->id, membershipPivot('member'));
    $this->user->update(['current_workspace_id' => $workspace->id]);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('the portal creates the configuration with the app features and sends the owner to Stripe', function () {
    $stripe = fakeBillingPortalStripe();

    $this->actingAs($this->user->fresh())
        ->get(route('app.billing.portal'))
        ->assertRedirect('https://billing.stripe.test/session');

    $create = billingPortalRequests($stripe, '/v1/billing_portal/configurations', 'POST');
    $create = collect($create)->first(
        fn (array $request): bool => preg_match('#/v1/billing_portal/configurations/[^/?]+#', $request['url']) !== 1
    );

    expect($create)->not->toBeNull();

    $features = data_get($create, 'params.features');

    expect(data_get($features, 'invoice_history.enabled'))->toBe('true')
        ->and(data_get($features, 'payment_method_update.enabled'))->toBe('true')
        ->and(data_get($features, 'subscription_update.enabled'))->toBe('false')
        ->and(data_get($features, 'subscription_cancel.enabled'))->toBe('true')
        ->and(data_get($features, 'subscription_cancel.mode'))->toBe('at_period_end')
        ->and(data_get($features, 'subscription_cancel.cancellation_reason.enabled'))->toBe('true')
        ->and(data_get($features, 'subscription_cancel.cancellation_reason.options'))->toContain('too_expensive');

    $session = collect(billingPortalRequests($stripe, '/v1/billing_portal/sessions', 'POST'))->first();

    expect(data_get($session, 'params.configuration'))->toBe('bpc_new')
        ->and(CatalogSetting::current()->stripe_billing_portal_configuration_id)->toBe('bpc_new');
});

test('a second open reuses the stored configuration and creates no other one', function () {
    $stripe = fakeBillingPortalStripe();

    $this->actingAs($this->user->fresh())->get(route('app.billing.portal'))->assertRedirect();
    $this->actingAs($this->user->fresh())->get(route('app.billing.portal'))->assertRedirect();

    expect(billingPortalRequests($stripe, '/v1/billing_portal/configurations', 'POST'))
        ->toHaveCount(2)
        ->and(collect(billingPortalRequests($stripe, '/v1/billing_portal/configurations', 'POST'))
            ->filter(fn (array $request): bool => preg_match('#/v1/billing_portal/configurations/[^/?]+#', $request['url']) === 1)
            ->count())->toBe(1)
        ->and(billingPortalRequests($stripe, '/v1/billing_portal/configurations/bpc_new', 'GET'))->toHaveCount(1)
        ->and(CatalogSetting::current()->stripe_billing_portal_configuration_id)->toBe('bpc_new');
});

test('a configuration missing from Stripe is replaced instead of failing the portal', function () {
    CatalogSetting::query()->create([
        'id' => CatalogSetting::SINGLETON_ID,
        'stripe_billing_portal_configuration_id' => 'bpc_old',
    ]);

    $stripe = fakeBillingPortalStripe(['bpc_old']);

    $this->actingAs($this->user->fresh())
        ->get(route('app.billing.portal'))
        ->assertRedirect('https://billing.stripe.test/session');

    expect(billingPortalRequests($stripe, '/v1/billing_portal/configurations/bpc_old', 'GET'))->toHaveCount(1)
        ->and(CatalogSetting::current()->stripe_billing_portal_configuration_id)->toBe('bpc_new');

    $session = collect(billingPortalRequests($stripe, '/v1/billing_portal/sessions', 'POST'))->first();

    expect(data_get($session, 'params.configuration'))->toBe('bpc_new');
});
