<?php

declare(strict_types=1);

use App\Models\Offer;
use App\Models\Operator;
use App\Models\Plan;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->siteHeaders = ['Authorization' => 'Bearer test-site-token'];
});

afterEach(fn () => Operator::forgetCurrent());

test('the site payload is the active catalog with prices in cents at the top level', function () {
    $response = $this->withHeaders($this->siteHeaders)->getJson(route('internal.site'));

    $response->assertOk()
        ->assertJsonStructure(['plans' => [['slug', 'name', 'quota_bytes', 'ai_credits_per_month', 'monthly', 'yearly']]])
        ->assertJsonPath('plans.0.slug', 'mini')
        ->assertJsonPath('plans.0.monthly', 1190)
        ->assertJsonPath('plans.0.yearly', 11900)
        ->assertJsonPath('plans.1.slug', 'standard')
        ->assertJsonPath('plans.2.slug', 'plus')
        ->assertJsonPath('plans.3.slug', 'max')
        ->assertJsonMissingPath('data');

    expect($response->json())->toHaveKey('plans')
        ->and($response->json('plans'))->toHaveCount(4);
});

test('an archived Plan never appears', function () {
    Plan::factory()->archived()->create(['slug' => 'legacy', 'name' => 'Legacy']);

    $response = $this->withHeaders($this->siteHeaders)->getJson(route('internal.site'));

    $response->assertOk()
        ->assertJsonCount(4, 'plans')
        ->assertJsonMissing(['slug' => 'legacy']);
});

test('the site carries the running general offer and only its covered prices', function () {
    $mini = Plan::query()->where('slug', 'mini')->firstOrFail();
    Offer::factory()->general()->percent(20)->covering([$mini], ['monthly'])->create(['name' => 'Spring sale']);
    Offer::factory()->link()->percent(50)->covering([$mini], ['monthly'])->create(['name' => 'Partner only']);

    $response = $this->withHeaders($this->siteHeaders)->getJson(route('internal.site'));

    $response->assertOk()
        ->assertJsonPath('offer.name', 'Spring sale')
        ->assertJsonPath('offer.discount.percent_off', 20)
        ->assertJsonPath('plans.0.slug', 'mini')
        ->assertJsonPath('plans.0.offer.monthly.regular', 1190)
        ->assertJsonPath('plans.0.offer.monthly.discounted', 952)
        ->assertJsonPath('plans.0.offer.yearly', null)
        ->assertJsonPath('plans.1.offer.monthly', null);
});

test('the site carries the current Operator and null when there is none', function () {
    Operator::forgetCurrent();
    Operator::query()->delete();

    $this->withHeaders($this->siteHeaders)->getJson(route('internal.site'))
        ->assertOk()
        ->assertJsonPath('operator', null);

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

    $this->withHeaders($this->siteHeaders)->getJson(route('internal.site'))
        ->assertOk()
        ->assertJsonStructure([
            'operator' => [
                'legal_name_latin',
                'address_latin',
                'legal_name_th',
                'address_th',
                'tax_id',
                'vat_id',
                'contact_email',
                'effective_from',
            ],
        ])
        ->assertJsonPath('operator.legal_name_latin', 'Acme Operator Co., Ltd.')
        ->assertJsonPath('operator.tax_id', '1234567890123')
        ->assertJsonPath('operator.vat_id', null)
        ->assertJsonMissingPath('operator.phone')
        ->assertJsonMissingPath('operator.id');
});
