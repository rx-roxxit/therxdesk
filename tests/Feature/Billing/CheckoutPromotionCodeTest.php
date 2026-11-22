<?php

declare(strict_types=1);

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Enums\Billing\Interval;
use App\Enums\User\Locale;
use App\Models\Account;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\User;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

beforeEach(function () {
    config(['cashier.secret' => 'sk_test_fake']);

    $this->socials = Plan::where('slug', 'mini')->firstOrFail();
    givePlanPrice($this->socials, Interval::Monthly, 'price_socials_monthly');

    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);

    $this->stripe = new class implements ClientInterface
    {
        /** @var array<string, mixed> */
        public array $session = [];

        /** @var list<array<string, mixed>> */
        public array $openSessions = [];

        /** @var list<string> */
        public array $expired = [];

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
        {
            if (preg_match('#/v1/checkout/sessions/([^/?]+)/expire#', $absUrl, $matches) === 1) {
                $this->expired[] = $matches[1];

                return [json_encode(['id' => $matches[1], 'object' => 'checkout.session', 'status' => 'expired']), 200, []];
            }

            if (strtoupper((string) $method) === 'GET' && str_contains($absUrl, '/v1/checkout/sessions')) {
                return [json_encode(['object' => 'list', 'data' => $this->openSessions, 'has_more' => false]), 200, []];
            }

            if (str_contains($absUrl, '/v1/checkout/sessions')) {
                $this->session = $params;

                return [json_encode(['id' => 'cs_fake', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.test/session']), 200, []];
            }

            if (str_contains($absUrl, '/v1/coupons/')) {
                return [json_encode(['id' => 'BF26_TEST', 'object' => 'coupon', 'percent_off' => 50, 'duration' => 'repeating', 'duration_in_months' => 3]), 200, []];
            }

            return [json_encode(['id' => 'cus_fake', 'object' => 'customer']), 200, []];
        }
    };

    ApiRequestor::setHttpClient($this->stripe);
});

test('a checkout without an offer charges the full price, takes the catalog trial and no promotion code', function () {
    $this->travelTo(now()->startOfSecond());

    app(StartSubscriptionCheckout::class)->redirect($this->account->fresh(), 'price_socials_monthly', route('app.welcome.plan'), $this->socials);

    expect(data_get($this->stripe->session, 'allow_promotion_codes'))->toBe('false')
        ->and(data_get($this->stripe->session, 'discounts'))->toBeNull()
        ->and((int) data_get($this->stripe->session, 'subscription_data.trial_end'))->toBe(now()->addDays(7)->getTimestamp());
});

test('the checkout carries the customer locale, the billing address and Terms consent', function () {
    $this->owner->update(['locale' => Locale::PortugueseBrazil]);

    app(StartSubscriptionCheckout::class)->redirect($this->account->fresh(), 'price_socials_monthly', route('app.welcome.plan'), $this->socials);

    expect(data_get($this->stripe->session, 'locale'))->toBe('pt-BR')
        ->and(data_get($this->stripe->session, 'billing_address_collection'))->toBe('required')
        ->and(data_get($this->stripe->session, 'consent_collection.terms_of_service'))->toBe('required');
});

test('an account that already had a trial gets no trial at checkout', function () {
    $this->account->update(['trial_ends_at' => now()->subDay()]);

    app(StartSubscriptionCheckout::class)->redirect($this->account->fresh(), 'price_socials_monthly', route('app.welcome.plan'), $this->socials);

    expect(data_get($this->stripe->session, 'subscription_data.trial_end'))->toBeNull();
});

test('a coupon offer sends its coupon and no promotion code field, which Stripe rejects together', function () {
    $offer = Offer::factory()->create(['stripe_coupon_id' => 'BF26_TEST']);

    app(StartSubscriptionCheckout::class)->redirect($this->account->fresh(), 'price_socials_monthly', route('app.welcome.plan'), $this->socials, $offer);

    expect(data_get($this->stripe->session, 'discounts.0.coupon'))->toBe('BF26_TEST')
        ->and(data_get($this->stripe->session, 'allow_promotion_codes'))->toBe('false')
        ->and(data_get($this->stripe->session, 'subscription_data.trial_end'))->not->toBeNull();
});

test('a trial offer that does not allow promotion codes sends the trial without the field', function () {
    $offer = Offer::factory()->trial(8)->create();

    app(StartSubscriptionCheckout::class)->redirect($this->account->fresh(), 'price_socials_monthly', route('app.welcome.plan'), $this->socials, $offer);

    expect(data_get($this->stripe->session, 'subscription_data.trial_end'))->not->toBeNull()
        ->and(data_get($this->stripe->session, 'allow_promotion_codes'))->toBe('false');
});

test('a new checkout expires the account other open sessions first', function () {
    $this->stripe->openSessions = [
        ['id' => 'cs_old', 'object' => 'checkout.session', 'status' => 'open'],
        ['id' => 'cs_older', 'object' => 'checkout.session', 'status' => 'open'],
    ];

    app(StartSubscriptionCheckout::class)->redirect($this->account->fresh(), 'price_socials_monthly', route('app.welcome.plan'), $this->socials);

    expect($this->stripe->expired)->toBe(['cs_old', 'cs_older'])
        ->and(data_get($this->stripe->session, 'subscription_data.trial_end'))->not->toBeNull();
});
