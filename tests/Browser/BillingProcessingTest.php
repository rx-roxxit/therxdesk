<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\ThaiPurchaseDocument;

function billingProcessingSubscriber(): User
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);
    $plan = Plan::query()->firstOrFail();
    $user->account->update(['plan_id' => $plan->id]);
    givePlanPrice($plan, Interval::Monthly, 'price_123');

    return $user;
}

test('once the subscription and plan are ready the processing page opens the schedule', function () {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(billingProcessingSubscriber());

    $schedulePath = route('app.posts.index', absolute: false);

    $page = visit(route('app.billing.processing'));
    waitForPath($page, $schedulePath);

    $page->assertPathIs($schedulePath)
        ->assertNoJavaScriptErrors();
});

test('a subscription started from billing returns to billing, not the onboarding', function () {
    config(['trypost.self_hosted' => false]);

    $user = billingProcessingSubscriber();
    $this->actingAs($user->fresh());

    $billingPath = route('app.billing.index', absolute: false);

    $page = visit(route('app.billing.processing', ['from' => 'billing']));
    waitForPath($page, $billingPath);

    $page->assertPathIs($billingPath)
        ->assertNoJavaScriptErrors();
});

test('on a phone the processing page opens the schedule too', function () {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(billingProcessingSubscriber());

    $schedulePath = route('app.posts.index', absolute: false);

    $page = visit(route('app.billing.processing'))->on()->mobile();
    waitForPath($page, $schedulePath);

    $page->assertPathIs($schedulePath)
        ->assertNoJavaScriptErrors();
});

test('while checkout settles the processing page uses the neutral auth layout, not the welcome one', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user);

    $page = visit(route('app.billing.processing'));
    waitFor($page, '@billing-processing');

    $page->assertSee('Processing your subscription')
        ->assertVisible('@billing-processing')
        ->assertVisible('@auth-logo')
        ->assertMissing('@welcome-back')
        ->assertMissing('@welcome-step-plan')
        ->assertNoJavaScriptErrors();
});

test('processing holds while the current checkout has not recorded its facts', function () {
    config(['trypost.self_hosted' => false]);

    $user = billingProcessingSubscriber();
    $this->actingAs($user->fresh());

    // The subscription and plan are ready, but no facts for this checkout are
    // on the account yet: leaving here is the race that misses the document.
    $page = visit(route('app.billing.processing', ['session_id' => 'cs_current']));
    waitFor($page, '@billing-processing');

    letPageRun($page, 2500);

    $page->assertVisible('@billing-processing')
        ->assertMissing('@thai-purchase-document')
        ->assertNoJavaScriptErrors();
});

test('an earlier checkout never counts as the current one', function () {
    config(['trypost.self_hosted' => false]);

    Operator::factory()->create([
        'legal_name_th' => 'บริษัท ตัวอย่าง จำกัด',
        'address_th' => '99 ถนนสุขุมวิท กรุงเทพมหานคร 10110',
    ]);
    Operator::forgetCurrent();

    $user = billingProcessingSubscriber();
    $this->actingAs($user->fresh());

    // The Thai facts on the account belong to an older checkout.
    $user->account->forceFill([
        'checkout_session_id' => 'cs_older',
        'billing_country' => 'TH',
    ])->save();

    $page = visit(route('app.billing.processing', ['session_id' => 'cs_current']));
    waitFor($page, '@billing-processing');

    letPageRun($page, 2500);

    $page->assertVisible('@billing-processing')
        ->assertMissing('@thai-purchase-document')
        ->assertNoJavaScriptErrors();
});

test('a current Thai checkout shows the document and Continue opens the schedule', function () {
    config(['trypost.self_hosted' => false]);

    Operator::factory()->create([
        'legal_name_th' => 'บริษัท ตัวอย่าง จำกัด',
        'address_th' => '99 ถนนสุขุมวิท กรุงเทพมหานคร 10110',
    ]);
    Operator::forgetCurrent();

    $user = billingProcessingSubscriber();
    $this->actingAs($user->fresh());

    $user->account->forceFill([
        'checkout_session_id' => 'cs_current',
        'billing_country' => 'TH',
        'billing_name' => 'Somchai Jaidee',
    ])->save();

    $schedulePath = route('app.posts.index', absolute: false);

    $page = visit(route('app.billing.processing', ['session_id' => 'cs_current']));
    waitFor($page, '@thai-purchase-document');

    $page->assertSee('บริษัท ตัวอย่าง จำกัด')
        ->assertSee(ThaiPurchaseDocument::CANCELLATION_TEXT)
        ->assertMissing('@billing-processing');

    $page->click('@purchase-document-continue');
    waitForPath($page, $schedulePath);

    $page->assertPathIs($schedulePath)
        ->assertNoJavaScriptErrors();
});

test('a current checkout outside Thailand opens the schedule with no document', function () {
    config(['trypost.self_hosted' => false]);

    $user = billingProcessingSubscriber();
    $this->actingAs($user->fresh());

    $user->account->forceFill([
        'checkout_session_id' => 'cs_current',
        'billing_country' => 'US',
    ])->save();

    $schedulePath = route('app.posts.index', absolute: false);

    $page = visit(route('app.billing.processing', ['session_id' => 'cs_current']));
    waitForPath($page, $schedulePath);

    $page->assertPathIs($schedulePath)
        ->assertMissing('@thai-purchase-document')
        ->assertNoJavaScriptErrors();
});
