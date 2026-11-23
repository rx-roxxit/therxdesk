<?php

declare(strict_types=1);

use App\Enums\User\Locale;
use App\Models\CatalogSetting;
use App\Models\Offer;
use App\Models\Operator;
use App\Models\Plan;
use App\Support\Availability\Availability;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->siteHeaders = ['Authorization' => 'Bearer test-site-token'];
    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
});

afterEach(fn () => Operator::forgetCurrent());

function sitePayload(): array
{
    return test()->withHeaders(test()->siteHeaders)
        ->getJson(route('internal.site'))
        ->assertOk()
        ->json();
}

test('the site payload is the active catalog with prices in cents at the top level', function () {
    $payload = sitePayload();

    expect(array_keys($payload))->toBe([
        'plans',
        'trial_days',
        'offer',
        'networks',
        'signups_open',
        'operator',
    ])->and($payload['trial_days'])->toBe(7)
        ->and($payload['offer'])->toBeNull()
        ->and($payload['signups_open'])->toBeFalse()
        ->and($payload['operator'])->toBeNull()
        ->and($payload['plans'])->toHaveCount(4);

    $plan = $payload['plans'][0];

    expect(array_keys($plan))->toBe([
        'slug',
        'name',
        'quota_bytes',
        'ai_credits_per_month',
        'monthly',
        'yearly',
        'offer',
    ])->and(array_keys($plan['offer']))->toBe(['monthly', 'yearly'])
        ->and($plan['slug'])->toBe('mini')
        ->and($plan['name'])->toBe('Mini')
        ->and($plan['quota_bytes'])->toBe(2147483648)
        ->and($plan['ai_credits_per_month'])->toBe(2500)
        ->and($plan['monthly'])->toBe(1190)
        ->and($plan['yearly'])->toBe(11900)
        ->and($plan['offer'])->toBe(['monthly' => null, 'yearly' => null]);
});

test('trial_days is the Catalog Trial length', function () {
    CatalogSetting::factory()->create(['trial_days' => 14]);

    expect(sitePayload()['trial_days'])->toBe(14);
});

test('networks are the networks a customer can connect now', function () {
    expect(sitePayload()['networks'])->toBe([
        'bluesky',
        'discord',
        'facebook',
        'google_business',
        'instagram',
        'linkedin',
        'mastodon',
        'pinterest',
        'telegram',
        'threads',
        'tiktok',
        'x',
        'youtube',
    ]);
});

test('a network switched off or not installed never appears', function () {
    app(Availability::class)->set('platform:tiktok', false);

    expect(sitePayload()['networks'])->not->toContain('tiktok');

    config(['trypost.platforms.pinterest.enabled' => false]);

    expect(sitePayload()['networks'])->not->toContain('pinterest');
});

test('signups_open follows the new_signups switch', function () {
    expect(sitePayload()['signups_open'])->toBeFalse();

    enableSignups();

    expect(sitePayload()['signups_open'])->toBeTrue();
});

test('an archived Plan never appears', function () {
    Plan::factory()->archived()->create(['slug' => 'legacy', 'name' => 'Legacy']);

    $payload = sitePayload();

    expect($payload['plans'])->toHaveCount(4)
        ->and(Arr::pluck($payload['plans'], 'slug'))->not->toContain('legacy');
});

test('the site carries the running general offer and only its covered prices', function () {
    $mini = Plan::query()->where('slug', 'mini')->firstOrFail();
    Offer::factory()->general()->percent(20)->covering([$mini], ['monthly'])->create(['name' => 'Spring sale']);

    $offer = sitePayload()['offer'];

    expect(array_keys($offer))->toBe([
        'slug',
        'name',
        'banner',
        'trial_days',
        'starts_at',
        'ends_at',
        'discount',
        'plan_ids',
        'intervals',
    ])->and($offer['name'])->toBe('Spring sale')
        ->and($offer['discount'])->toBe([
            'type' => 'percent',
            'percent_off' => 20,
            'amount_off' => null,
            'currency' => 'usd',
            'duration' => 'once',
            'duration_in_months' => null,
        ]);

    $payload = sitePayload();

    expect($payload['plans'][0]['slug'])->toBe('mini')
        ->and($payload['plans'][0]['offer']['monthly'])->toBe([
            'regular' => 1190,
            'discount' => 238,
            'discounted' => 952,
        ])
        ->and($payload['plans'][0]['offer']['yearly'])->toBeNull()
        ->and($payload['plans'][1]['offer']['monthly'])->toBeNull();
});

test('a link Offer never appears on the site', function () {
    Offer::factory()->link()->percent(50)->create(['name' => 'Partner only']);

    expect(sitePayload()['offer'])->toBeNull();
});

test('the offer banner is rendered in every locale and leaves the app locale alone', function () {
    Offer::factory()->general()->percent(20)->create(['name' => 'Spring sale']);

    $banner = sitePayload()['offer']['banner'];

    expect(array_keys($banner))->toBe(Locale::values())
        ->and($banner['en'])->toBe('20% off your first month');

    foreach ($banner as $locale => $line) {
        expect($line)->toBeString()->not->toBe('')
            ->and($line)->not->toContain('rxdesk.')
            ->and($line)->not->toContain(':discount');
    }

    expect(App::currentLocale())->toBe(Locale::DEFAULT->value);
});

test('the site carries the current Operator and null when there is none', function () {
    Operator::forgetCurrent();
    Operator::query()->delete();

    expect(sitePayload()['operator'])->toBeNull();

    Operator::factory()->create([
        'legal_name_latin' => 'Acme Operator Co., Ltd.',
        'address_latin' => '1 Test Road, Bangkok',
        'legal_name_th' => 'บริษัท แอคมี จำกัด',
        'address_th' => '123 ถนนทดสอบ กรุงเทพมหานคร',
        'tax_id' => '1234567890123',
        'vat_id' => null,
        'contact_email' => 'billing@example.com',
        'phone' => '555-0100',
    ]);

    Operator::forgetCurrent();

    $operator = sitePayload()['operator'];

    expect(array_keys($operator))->toBe([
        'legal_name_latin',
        'address_latin',
        'legal_name_th',
        'address_th',
        'tax_id',
        'vat_id',
        'contact_email',
        'effective_from',
    ])->and($operator['legal_name_latin'])->toBe('Acme Operator Co., Ltd.')
        ->and($operator['tax_id'])->toBe('1234567890123')
        ->and($operator['vat_id'])->toBeNull();
});

test('the site endpoint is read-only', function () {
    $this->getJson(route('internal.site'))->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->postJson(route('internal.site'))
        ->assertMethodNotAllowed();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.site'))
        ->assertMethodNotAllowed();
});
