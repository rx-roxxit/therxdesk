<?php

declare(strict_types=1);

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Enums\Billing\Interval;
use App\Enums\User\Goal;
use App\Enums\User\Persona;
use App\Enums\User\PreviousSoftware;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    $this->plan = Plan::factory()->create(['slug' => 'socials', 'name' => 'Socials']);
    givePlanPrice($this->plan, Interval::Monthly, 'price_socials_monthly');

    $this->account = Account::factory()->create([
        'stripe_id' => 'cus_retry',
        'trial_ends_at' => null,
        'plan_id' => $this->plan->id,
    ]);
    $this->user = User::factory()->create([
        'account_id' => $this->account->id,
        'persona' => Persona::Creator->value,
        'goals' => [Goal::SaveTime->value],
        'previous_software' => PreviousSoftware::Buffer->value,
    ]);
    $this->account->update(['owner_id' => $this->user->id]);
});

test('an account whose first charge failed sees the retry step instead of a new checkout', function () {
    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_retry',
        'stripe_status' => 'past_due',
        'stripe_price' => 'price_socials_monthly',
    ]);

    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome/Plan', false)
            ->where('retry.plan', 'Socials')
            ->where('retry.update_card_url', route('app.welcome.plan.portal'))
        );
});

test('the plan step has no retry when no first charge is pending', function () {
    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('retry', null));
});

test('processing sends an account whose first charge failed back to the plan step', function () {
    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_retry',
        'stripe_status' => 'past_due',
        'stripe_price' => 'price_socials_monthly',
    ]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.billing.processing'))
        ->assertRedirect(route('app.welcome.plan'));
});
