<?php

declare(strict_types=1);

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Enums\User\Goal;
use App\Enums\User\Persona;
use App\Enums\User\PreviousSoftware;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    $this->owner = User::factory()->create([
        'persona' => Persona::Creator->value,
        'goals' => [Goal::SaveTime->value],
        'previous_software' => PreviousSoftware::None->value,
    ]);

    $this->account = $this->owner->account;
    $this->standard = Plan::where('slug', 'standard')->firstOrFail();

    $this->account->forceFill([
        'plan_id' => $this->standard->id,
        'complimentary_since' => now(),
        'complimentary_until' => null,
    ])->save();
});

$withWorkspace = function (User $user): Workspace {
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    return $workspace;
};

test('the plan step shows the Complimentary state without plan cards', function () {
    $this->actingAs($this->owner)
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome/Plan', false)
            ->has('plans', 0)
            ->where('selected.plan', 'standard')
            ->where('selected.interval', 'monthly')
            ->where('offer', null)
            ->where('trial', null)
            ->where('retry', null)
            ->where('complimentary.plan_name', 'Standard')
            ->where('complimentary.ends_at', null)
        );
});

test('storing a plan is refused with a flash error and starts no checkout', function () {
    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->owner)
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertSessionHas('flash.error', __('rxdesk.complimentary.checkout_blocked'));

    expect($this->account->fresh()->plan_id)->toBe($this->standard->id);
});

test('billing shows the Complimentary state and no price', function () use ($withWorkspace) {
    $withWorkspace($this->owner);

    $this->actingAs($this->owner->fresh())
        ->get(route('app.billing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/account/Billing', false)
            ->where('plan.slug', 'standard')
            ->where('hasSubscription', false)
            ->where('onTrial', false)
            ->where('complimentary.plan_name', 'Standard')
            ->where('complimentary.ends_at', null)
        );
});

test('changing plan is refused with a flash error and leaves the Plan alone', function () use ($withWorkspace) {
    $withWorkspace($this->owner);
    $mini = Plan::where('slug', 'mini')->firstOrFail();

    $this->actingAs($this->owner->fresh())
        ->post(route('app.billing.change-plan'), [
            'plan_id' => $mini->id,
            'interval' => 'monthly',
        ])
        ->assertSessionHas('flash.error', __('rxdesk.complimentary.checkout_blocked'));

    expect($this->account->fresh()->plan_id)->toBe($this->standard->id);
});

test('subscribing now is refused with a flash error and leaves the Plan alone', function () use ($withWorkspace) {
    $withWorkspace($this->owner);

    $this->actingAs($this->owner->fresh())
        ->post(route('app.billing.checkout'))
        ->assertSessionHas('flash.error', __('rxdesk.complimentary.checkout_blocked'));

    expect($this->account->fresh()->plan_id)->toBe($this->standard->id);
});

test('the Customer Portal is refused and sends the Owner back to billing', function () use ($withWorkspace) {
    $withWorkspace($this->owner);

    $this->actingAs($this->owner->fresh())
        ->get(route('app.billing.portal'))
        ->assertRedirect(route('app.billing.index'))
        ->assertSessionHas('flash.error', __('rxdesk.complimentary.checkout_blocked'));
});

test('after the end date the Account is held at the plan step with cards again', function () use ($withWorkspace) {
    $withWorkspace($this->owner);
    $this->account->forceFill(['complimentary_until' => now()->addDay()])->save();
    Carbon::setTestNow(now()->addDays(31));

    $this->actingAs($this->owner->fresh())
        ->get(route('app.posts.index'))
        ->assertRedirect(route('app.welcome.plan'));

    $this->actingAs($this->owner->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome/Plan', false)
            ->has('plans', 4)
            ->where('complimentary', null)
        );
});
