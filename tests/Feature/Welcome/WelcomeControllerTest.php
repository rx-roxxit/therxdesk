<?php

declare(strict_types=1);

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Enums\Billing\Interval;
use App\Enums\PostHog\CheckoutEvent;
use App\Enums\PostHog\WelcomeEvent;
use App\Enums\User\Goal;
use App\Enums\User\Locale;
use App\Enums\User\Persona;
use App\Enums\User\PreviousSoftware;
use App\Http\Middleware\App\CapturePlan;
use App\Jobs\PostHog\SendEvent;
use App\Models\Account;
use App\Models\CatalogSetting;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
    $this->user = User::factory()->create();
});

test('welcome redirects to the persona step', function () {
    $this->actingAs($this->user)
        ->get(route('app.welcome'))
        ->assertRedirect(route('app.welcome.persona'));
});

test('persona renders for an unsubscribed account', function () {
    $this->actingAs($this->user)
        ->get(route('app.welcome.persona'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome/Persona', false)
            ->has('personas', count(Persona::cases()))
        );
});

test('persona requires a valid selection', function (array $payload) {
    $this->actingAs($this->user)
        ->post(route('app.welcome.persona.store'), $payload)
        ->assertSessionHasErrors('persona');

    expect($this->user->fresh()->persona)->toBeNull();
})->with([
    'missing' => [[]],
    'invalid' => [['persona' => 'not-a-persona']],
]);

test('persona store saves the selection mirrors it to PostHog and advances to goals', function () {
    config(['services.posthog.enabled' => true, 'services.posthog.api_key' => 'phc_test']);
    Bus::fake();

    $this->actingAs($this->user)
        ->post(route('app.welcome.persona.store'), ['persona' => Persona::Agency->value])
        ->assertRedirect(route('app.welcome.goals'));

    expect($this->user->fresh()->persona)->toBe(Persona::Agency);
    Bus::assertDispatched(SendEvent::class, fn (SendEvent $event): bool => $event->method === 'capture'
        && data_get($event->payload, 'distinctId') === $this->user->id
        && data_get($event->payload, 'event') === WelcomeEvent::Persona->value
        && data_get($event->payload, 'properties.persona') === Persona::Agency->value);
});

test('goals redirects to persona until a persona is selected', function () {
    $this->actingAs($this->user)
        ->get(route('app.welcome.goals'))
        ->assertRedirect(route('app.welcome.persona'));
});

test('goals renders after a persona is selected', function () {
    $this->user->update(['persona' => Persona::Agency->value]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.goals'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome/Goals', false)
            ->has('goals', count(Goal::cases()))
        );
});

test('goals requires at least one valid goal', function (array $goals, string $error) {
    $this->user->update(['persona' => Persona::Agency->value]);

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.goals.store'), ['goals' => $goals])
        ->assertSessionHasErrors($error);

    expect($this->user->fresh()->goals)->toBeNull();
})->with([
    'empty' => [[], 'goals'],
    'invalid' => [['not-a-goal'], 'goals.0'],
]);

test('goals store saves choices mirrors them to PostHog and advances to software', function () {
    config(['services.posthog.enabled' => true, 'services.posthog.api_key' => 'phc_test']);
    Bus::fake();
    $this->user->update(['persona' => Persona::Creator->value]);

    $goals = [Goal::UseMcp->value, Goal::SaveTime->value];

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.goals.store'), ['goals' => $goals])
        ->assertRedirect(route('app.welcome.software'));

    expect($this->user->fresh()->goals)->toBe($goals);
    Bus::assertDispatched(SendEvent::class, fn (SendEvent $event): bool => $event->method === 'capture'
        && data_get($event->payload, 'event') === WelcomeEvent::Goals->value
        && data_get($event->payload, 'properties.goals') === $goals);
});

test('software redirects to goals until a goal is selected', function () {
    $this->user->update(['persona' => Persona::Agency->value]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.software'))
        ->assertRedirect(route('app.welcome.goals'));
});

test('software renders after goals are selected', function () {
    completeWelcomeThroughGoals($this->user);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.software'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome/Software', false)
            ->has('software', count(PreviousSoftware::cases()))
            ->where('software.0', ['value' => 'buffer', 'name' => 'Buffer'])
            ->where('software.10', ['value' => 'none', 'name' => null])
            ->where('selected', null)
        );
});

test('software requires a valid selection', function (array $payload) {
    completeWelcomeThroughGoals($this->user);

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.software.store'), $payload)
        ->assertSessionHasErrors('previous_software');

    expect($this->user->fresh()->previous_software)->toBeNull();
})->with([
    'missing' => [[]],
    'invalid' => [['previous_software' => 'not-a-tool']],
]);

test('software store saves the selection, mirrors it to PostHog and advances to plan', function (PreviousSoftware $software) {
    config(['services.posthog.enabled' => true, 'services.posthog.api_key' => 'phc_test']);
    Bus::fake();
    completeWelcomeThroughGoals($this->user);
    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.software.store'), ['previous_software' => $software->value])
        ->assertRedirect(route('app.welcome.plan'));

    expect($this->user->fresh()->previous_software)->toBe($software);
    Bus::assertDispatched(SendEvent::class, fn (SendEvent $event): bool => $event->method === 'identify'
        && data_get($event->payload, 'properties.previous_software') === $software->value);
    Bus::assertDispatched(SendEvent::class, fn (SendEvent $event): bool => $event->method === 'capture'
        && data_get($event->payload, 'event') === WelcomeEvent::Software->value
        && data_get($event->payload, 'properties.previous_software') === $software->value);
})->with([
    'a competitor' => [PreviousSoftware::Metricool],
    'no tool yet' => [PreviousSoftware::None],
]);

test('plan redirects to software until a tool is selected', function () {
    completeWelcomeThroughGoals($this->user);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertRedirect(route('app.welcome.software'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertRedirect(route('app.welcome.software'));
});

test('plan shows every active plan in catalog order with its labels and the trial', function () {
    $this->travelTo(now()->startOfSecond());
    completeWelcomeThroughSoftware($this->user);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome/Plan', false)
            ->has('plans', 4)
            ->where('plans.0.slug', 'mini')
            ->where('plans.0.name', 'Mini')
            ->where('plans.0.workspace_limit', null)
            ->where('plans.0.quota_bytes', 2147483648)
            ->where('plans.0.quota_label', '2 GB')
            ->where('plans.0.ai_credits_per_month', 2500)
            ->where('plans.0.ai_credits_label', '2,500')
            ->where('plans.0.monthly_amount', 1190)
            ->where('plans.0.yearly_amount', 11900)
            ->where('plans.1.slug', 'standard')
            ->where('plans.2.slug', 'plus')
            ->where('plans.3.slug', 'max')
            ->where('selected.plan', 'mini')
            ->where('selected.interval', 'monthly')
            ->where('offer', null)
            ->where('trial.days', 7)
            ->where('trial.first_charge_at', now()->addDays(7)->toIso8601String())
        );
});

test('the trial days come from the catalog', function () {
    CatalogSetting::factory()->create(['id' => 1, 'trial_days' => 14]);
    completeWelcomeThroughSoftware($this->user);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertInertia(fn ($page) => $page->where('trial.days', 14));
});

test('the plan step preselects the plan and interval kept from the register link', function () {
    completeWelcomeThroughSoftware($this->user);

    $this->actingAs($this->user->fresh())
        ->withSession([
            CapturePlan::PLAN_SESSION_KEY => 'plus',
            CapturePlan::INTERVAL_SESSION_KEY => 'yearly',
        ])
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.plan', 'plus')
            ->where('selected.interval', 'yearly')
        );
});

test('the plan step drops a kept plan or interval that is no longer valid', function () {
    completeWelcomeThroughSoftware($this->user);

    $this->actingAs($this->user->fresh())
        ->withSession([
            CapturePlan::PLAN_SESSION_KEY => 'enterprise',
            CapturePlan::INTERVAL_SESSION_KEY => 'weekly',
        ])
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.plan', 'mini')
            ->where('selected.interval', 'monthly')
        )
        ->assertSessionMissing(CapturePlan::PLAN_SESSION_KEY)
        ->assertSessionMissing(CapturePlan::INTERVAL_SESSION_KEY);
});

test('an account that had a trial gets no new one', function (Closure $setup) {
    completeWelcomeThroughSoftware($this->user);
    $setup($this->user->account->fresh());

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertInertia(fn ($page) => $page->where('trial', null));
})->with([
    'a generic trial' => [fn (Account $account) => $account->update(['trial_ends_at' => now()->subDay()])],
    'a subscription that started on a trial' => [fn (Account $account) => $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_trial',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_monthly_test',
        'trial_ends_at' => now()->subMonth(),
        'ends_at' => now()->subDay(),
    ])],
    'a cancelled billable subscription' => [fn (Account $account) => $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_canceled',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_monthly_test',
        'ends_at' => now()->subDay(),
    ])],
]);

test('a lapsed account sees the date its data will be deleted', function () {
    completeWelcomeThroughSoftware($this->user);

    $account = $this->user->account;
    $account->forceFill(['first_paid_at' => now()->subMonth()])->save();
    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_lapsed',
        'stripe_status' => 'active',
        'stripe_price' => 'price_lapsed',
        'ends_at' => now()->subDay(),
    ]);
    $account->forceFill(['deletion_scheduled_at' => now()->addDays(20)])->save();

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('lapsedDeletion.date', $account->fresh()->deletion_scheduled_at->toIso8601String())
        );
});

test('a never-paid signup gets no deletion line', function () {
    completeWelcomeThroughSoftware($this->user);
    $this->user->account->forceFill(['deletion_scheduled_at' => now()->addDays(20)])->save();

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('lapsedDeletion', null));
});

test('plan sends a claimed trial offer only to an account that may use it', function (Closure $setup, bool $claims, bool $offered) {
    $trial = Offer::factory()->freeTrial()->create(['slug' => 'long-trial']);
    completeWelcomeThroughSoftware($this->user);
    $setup($this->user);

    if ($claims) {
        $this->user->account->offers()->attach($trial, ['claimed_at' => now()]);
    }

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $offered
            ? $page->where('offer.slug', 'long-trial')
            : $page->where('offer', null));
})->with([
    'no claim pays the full price' => [fn () => null, false, false],
    'first-time subscriber' => [fn () => null, true, true],
    'retrying an incomplete checkout' => [fn (User $user) => $user->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_incomplete',
        'stripe_status' => 'incomplete',
        'stripe_price' => 'price_monthly_test',
        'quantity' => 1,
    ]), true, true],
    'former subscriber' => [fn (User $user) => $user->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_canceled',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_monthly_test',
        'quantity' => 1,
        'ends_at' => now()->subDay(),
    ]), true, true],
]);

test('the plan step prices only the covered plans and intervals', function () {
    completeWelcomeThroughSoftware($this->user);
    $mini = Plan::query()->where('slug', 'mini')->firstOrFail();
    $offer = Offer::factory()->percent(50)->covering([$mini], ['monthly'])->create();
    $this->user->account->offers()->attach($offer, ['claimed_at' => now()]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.offer.monthly.badge', '50%')
            ->where('plans.0.offer.monthly.regular', '$11.90')
            ->where('plans.0.offer.monthly.discounted', '$5.95')
            ->where('plans.0.offer.monthly.line', '50% off your first month')
            ->where('plans.0.offer.yearly', null)
            ->where('plans.1.offer.monthly', null)
            ->where('plans.1.offer.yearly', null)
        );
});

test('the longer offer trial reaches only the covered plan and interval', function () {
    completeWelcomeThroughSoftware($this->user);
    $mini = Plan::query()->where('slug', 'mini')->firstOrFail();
    $offer = Offer::factory()->trial(14)->covering([$mini], ['monthly'])->create();
    $this->user->account->offers()->attach($offer, ['claimed_at' => now()]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.trial.monthly.days', 14)
            ->where('plans.0.trial.yearly.days', 7)
            ->where('plans.1.trial.monthly.days', 7)
        );
});

test('a general offer is offered without a claim', function () {
    completeWelcomeThroughSoftware($this->user);
    $offer = Offer::factory()->general()->freeTrial()->create(['slug' => 'spring-trial']);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('offer.slug', $offer->slug));
});

test('plan store requires a plan and an interval', function () {
    completeWelcomeThroughSoftware($this->user);
    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'))
        ->assertSessionHasErrors(['plan', 'interval']);
});

test('plan store sends the owner to Stripe checkout on the selected plan and monthly price', function () {
    completeWelcomeThroughSoftware($this->user);
    $socials = Plan::where('slug', 'mini')->firstOrFail();
    givePlanPrice($socials, Interval::Monthly, 'price_socials_monthly');
    givePlanPrice($socials, Interval::Yearly, 'price_socials_yearly');

    $this->mock(StartSubscriptionCheckout::class)
        ->shouldReceive('redirect')
        ->once()
        ->withArgs(fn ($account, $priceId, $cancelUrl, $plan): bool => $account->is($this->user->account)
            && $priceId === 'price_socials_monthly'
            && $cancelUrl === route('app.welcome.plan')
            && $plan->is($socials))
        ->andReturn(redirect('https://checkout.stripe.test/session'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertRedirect('https://checkout.stripe.test/session')
        ->assertSessionHas(CapturePlan::PLAN_SESSION_KEY, 'mini')
        ->assertSessionHas(CapturePlan::INTERVAL_SESSION_KEY, 'monthly');
});

test('plan store refuses while the first charge of a trial is being retried', function () {
    completeWelcomeThroughSoftware($this->user);
    givePlanPrice(Plan::where('slug', 'mini')->firstOrFail(), Interval::Monthly, 'price_socials_monthly');

    $this->user->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_past_due',
        'stripe_status' => 'past_due',
        'stripe_price' => 'price_socials_monthly',
    ]);

    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertRedirect(route('app.welcome.plan'));
});

test('plan store opens the yearly price when yearly is picked', function () {
    completeWelcomeThroughSoftware($this->user);
    $plus = Plan::where('slug', 'plus')->firstOrFail();
    givePlanPrice($plus, Interval::Monthly, 'price_plus_monthly');
    givePlanPrice($plus, Interval::Yearly, 'price_plus_yearly');

    $this->mock(StartSubscriptionCheckout::class)
        ->shouldReceive('redirect')
        ->once()
        ->withArgs(fn ($account, $priceId, $cancelUrl, $plan): bool => $priceId === 'price_plus_yearly'
            && $plan->is($plus))
        ->andReturn(redirect('https://checkout.stripe.test/session'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'), ['plan' => 'plus', 'interval' => 'yearly'])
        ->assertRedirect('https://checkout.stripe.test/session')
        ->assertSessionHas(CapturePlan::PLAN_SESSION_KEY, 'plus')
        ->assertSessionHas(CapturePlan::INTERVAL_SESSION_KEY, 'yearly');
});

test('an unverified owner cannot start checkout', function () {
    $this->user->update(['email_verified_at' => null]);
    completeWelcomeThroughSoftware($this->user);
    givePlanPrice(Plan::where('slug', 'mini')->firstOrFail(), Interval::Monthly, 'price_socials_monthly');

    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertStatus(403);
});

test('plan store fails loudly when the selected plan price is not configured', function () {
    completeWelcomeThroughSoftware($this->user);
    Plan::where('slug', 'mini')->firstOrFail()->priceFor(Interval::Monthly)?->update(['stripe_price_id' => null]);

    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertStatus(500);
});

test('a welcome step stores a locale change and stays on the page', function () {
    completeWelcomeThroughGoals($this->user);

    $this->actingAs($this->user->fresh())
        ->from(route('app.welcome.software'))
        ->put(route('app.profile.language'), ['locale' => 'pt-BR'])
        ->assertRedirect(route('app.welcome.software'))
        ->assertCookieMissing('locale');

    expect($this->user->fresh()->locale)->toBe(Locale::PortugueseBrazil);
});

test('completed welcome steps remain reachable when going back', function () {
    attachCurrentWorkspace($this->user);
    $this->user->update([
        'persona' => Persona::Agency->value,
        'goals' => [Goal::SaveTime->value],
        'previous_software' => PreviousSoftware::Buffer->value,
    ]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.persona'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome/Persona', false));

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.goals'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome/Goals', false));

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.software'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome/Software', false)->where('selected', PreviousSoftware::Buffer->value));

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome/Plan', false));
});

test('welcome funnel captures every step up to the plan, in order', function () {
    config(['services.posthog.enabled' => true, 'services.posthog.api_key' => 'phc_test']);
    Bus::fake();
    $workspace = attachCurrentWorkspace($this->user);
    SocialAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id]);

    $plan = Plan::where('slug', 'mini')->firstOrFail();
    givePlanPrice($plan, Interval::Monthly, 'price_monthly_test');

    $this->mock(StartSubscriptionCheckout::class)
        ->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://checkout.stripe.test/session'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.persona.store'), ['persona' => Persona::Agency->value])
        ->assertRedirect(route('app.welcome.goals'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.goals.store'), ['goals' => [Goal::SaveTime->value]])
        ->assertRedirect(route('app.welcome.software'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.software.store'), ['previous_software' => PreviousSoftware::None->value])
        ->assertRedirect(route('app.welcome.plan'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertRedirect('https://checkout.stripe.test/session');

    $funnel = array_values(array_diff(WelcomeEvent::funnel(), [CheckoutEvent::Started->value]));

    $captured = collect(Bus::dispatched(SendEvent::class))
        ->filter(fn (SendEvent $event): bool => $event->method === 'capture')
        ->map(fn (SendEvent $event): string => (string) data_get($event->payload, 'event'))
        ->filter(fn (string $event): bool => in_array($event, $funnel, true))
        ->values()
        ->all();

    expect($captured)->toBe($funnel);
});

test('welcome steps no longer share a workspace summary', function () {
    $this->actingAs($this->user)
        ->get(route('app.welcome.persona'))
        ->assertInertia(fn ($page) => $page
            ->component('welcome/Persona', false)
            ->missing('welcome')
        );
});

test('welcome steps redirect to calendar for subscribed accounts', function (string $routeName, string $method, array $payload = []) {
    subscribeAccount($this->user->account);

    $this->actingAs($this->user->fresh());

    $response = $method === 'get'
        ? $this->get(route($routeName))
        : $this->post(route($routeName), $payload);

    $response->assertRedirect(route('app.calendar'));
})->with([
    'persona' => ['app.welcome.persona', 'get'],
    'persona store' => ['app.welcome.persona.store', 'post', ['persona' => Persona::Agency->value]],
    'goals' => ['app.welcome.goals', 'get'],
    'goals store' => ['app.welcome.goals.store', 'post', ['goals' => [Goal::SaveTime->value]]],
    'software' => ['app.welcome.software', 'get'],
    'software store' => ['app.welcome.software.store', 'post', ['previous_software' => PreviousSoftware::Buffer->value]],
    'plan' => ['app.welcome.plan', 'get'],
    'plan store' => ['app.welcome.plan.store', 'post', ['plan' => 'mini', 'interval' => 'monthly']],
]);

test('welcome steps redirect to calendar in self hosted mode', function (string $routeName, string $method, array $payload = []) {
    config(['trypost.self_hosted' => true]);

    $this->actingAs($this->user);

    $response = $method === 'get'
        ? $this->get(route($routeName))
        : $this->post(route($routeName), $payload);

    $response->assertRedirect(route('app.calendar'));
})->with([
    'persona' => ['app.welcome.persona', 'get'],
    'persona store' => ['app.welcome.persona.store', 'post', ['persona' => Persona::Agency->value]],
    'goals' => ['app.welcome.goals', 'get'],
    'goals store' => ['app.welcome.goals.store', 'post', ['goals' => [Goal::SaveTime->value]]],
    'software' => ['app.welcome.software', 'get'],
    'software store' => ['app.welcome.software.store', 'post', ['previous_software' => PreviousSoftware::Buffer->value]],
    'plan' => ['app.welcome.plan', 'get'],
    'plan store' => ['app.welcome.plan.store', 'post', ['plan' => 'mini', 'interval' => 'monthly']],
]);

test('old onboarding routes are not registered', function (string $routeName) {
    expect(Route::has($routeName))->toBeFalse();
})->with([
    'index' => 'app.onboarding',
    'store' => 'app.onboarding.store',
    'skip mcp' => 'app.onboarding.mcp.skip',
    'complete' => 'app.onboarding.complete',
    'goals' => 'app.onboarding.goals',
    'goals store' => 'app.onboarding.goals.store',
    'connect' => 'app.onboarding.connect',
    'welcome connect' => 'app.welcome.connect',
    'welcome connect store' => 'app.welcome.connect.store',
    'welcome referral source' => 'app.welcome.referral-source',
    'welcome mcp' => 'app.welcome.mcp',
    'welcome mcp connect click' => 'app.welcome.mcp.connect-click',
    'welcome mcp skip' => 'app.welcome.mcp.skip',
    'checkout' => 'app.onboarding.checkout',
]);

test('members cannot start Stripe checkout from welcome', function (bool $withWorkspace) {
    $member = User::factory()->create(['account_id' => $this->user->account_id]);
    completeWelcomeThroughGoals($member);

    if ($withWorkspace) {
        attachCurrentWorkspace($member);
    }

    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($member->fresh())
        ->post(route('app.welcome.software.store'), ['previous_software' => PreviousSoftware::Buffer->value])
        ->assertRedirect(route('app.welcome.subscription-required'));
})->with([
    'without workspace' => [false],
    'with empty workspace' => [true],
]);

test('members without app access are held on the subscription required screen', function (string $routeName, string $method, array $payload = []) {
    $member = User::factory()->create(['account_id' => $this->user->account_id]);

    $this->actingAs($member->fresh());

    $response = $method === 'get'
        ? $this->get(route($routeName))
        : $this->post(route($routeName), $payload);

    $response->assertRedirect(route('app.welcome.subscription-required'));
})->with([
    'persona' => ['app.welcome.persona', 'get'],
    'persona store' => ['app.welcome.persona.store', 'post', ['persona' => Persona::Agency->value]],
    'goals' => ['app.welcome.goals', 'get'],
    'goals store' => ['app.welcome.goals.store', 'post', ['goals' => [Goal::SaveTime->value]]],
    'software' => ['app.welcome.software', 'get'],
    'software store' => ['app.welcome.software.store', 'post', ['previous_software' => PreviousSoftware::Buffer->value]],
    'plan' => ['app.welcome.plan', 'get'],
    'plan store' => ['app.welcome.plan.store', 'post', ['plan' => 'mini', 'interval' => 'monthly']],
]);

test('subscription required screen renders for members without app access', function () {
    $member = User::factory()->create(['account_id' => $this->user->account_id]);

    $this->actingAs($member->fresh())
        ->get(route('app.welcome.subscription-required'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome/SubscriptionRequired', false)
            ->where('ownerName', $this->user->name)
        );
});

test('subscription required screen sends owners back to the welcome flow', function () {
    $this->actingAs($this->user)
        ->get(route('app.welcome.subscription-required'))
        ->assertRedirect(route('app.welcome.plan'));
});

test('subscription required screen sends subscribed users to the calendar', function () {
    subscribeAccount($this->user->account);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.subscription-required'))
        ->assertRedirect(route('app.calendar'));
});

test('subscription required screen sends members with app access to the calendar', function () {
    ['owner' => $owner, 'member' => $member] = strandedMemberOnSharedAccount();
    subscribeAccount($owner->account);

    $this->actingAs($member)
        ->get(route('app.welcome.subscription-required'))
        ->assertRedirect(route('app.calendar'));
});

test('subscription required screen redirects to calendar in self hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $member = User::factory()->create(['account_id' => $this->user->account_id]);

    $this->actingAs($member->fresh())
        ->get(route('app.welcome.subscription-required'))
        ->assertRedirect(route('app.calendar'));
});

test('welcome sends members with app access to the calendar', function () {
    ['owner' => $owner, 'member' => $member] = strandedMemberOnSharedAccount();
    subscribeAccount($owner->account);

    $this->actingAs($member)
        ->get(route('app.welcome.persona'))
        ->assertRedirect(route('app.calendar'));
});

function completeWelcomeThroughGoals(User $user): void
{
    $user->update([
        'persona' => Persona::Agency->value,
        'goals' => [Goal::SaveTime->value],
    ]);
}

function completeWelcomeThroughSoftware(User $user): void
{
    completeWelcomeThroughGoals($user);
    $user->update(['previous_software' => PreviousSoftware::Buffer->value]);
}

function attachCurrentWorkspace(User $user): Workspace
{
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    return $workspace;
}

test('the plan step sends the current offer with its name, badge and lines', function () {
    completeWelcomeThroughSoftware($this->user);
    $offer = Offer::factory()->percent(20)->repeating(3)->create(['slug' => 'spring-sale', 'name' => 'Spring sale']);
    $this->user->account->offers()->attach($offer, ['claimed_at' => now()]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('offer.slug', 'spring-sale')
            ->where('offer.name', 'Spring sale')
            ->where('offer.show_banner', false)
            ->where('offer.discount_label', '20%')
            ->where('offer.lines.monthly', '20% off your first 3 months')
            ->where('offer.lines.yearly', '20% off your first year')
        );
});

test('the plan step sends no offer once it expired', function () {
    completeWelcomeThroughSoftware($this->user);
    $offer = Offer::factory()->create(['ends_at' => now()->addHour()]);
    $this->user->account->offers()->attach($offer, ['claimed_at' => now()]);

    $this->travel(2)->hours();

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('offer', null)
        );
});

test('plan store hands checkout the offer it tracked', function () {
    completeWelcomeThroughSoftware($this->user);
    givePlanPrice(Plan::where('slug', 'mini')->firstOrFail(), Interval::Monthly, 'price_socials_monthly');
    $offer = Offer::factory()->create();
    $this->user->account->offers()->attach($offer, ['claimed_at' => now()]);

    $this->mock(StartSubscriptionCheckout::class)
        ->shouldReceive('redirect')
        ->once()
        ->withArgs(fn ($account, $priceId, $cancelUrl, $plan, $checkoutOffer = null): bool => $checkoutOffer?->is($offer) === true)
        ->andReturn(redirect('https://checkout.stripe.test/session'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertRedirect('https://checkout.stripe.test/session');
});

test('the plan step sends show_banner only for an offer that shows one', function (bool $showBanner) {
    completeWelcomeThroughSoftware($this->user);
    $offer = Offer::factory()->percent(50)->repeating(3)->create([
        'slug' => 'black-friday-2026',
        'name' => 'Black Friday',
        'show_banner' => $showBanner,
    ]);
    $this->user->account->offers()->attach($offer, ['claimed_at' => now()]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertInertia(fn ($page) => $page
            ->where('offer.show_banner', $showBanner)
            ->where('offer.lines.monthly', '50% off your first 3 months'));
})->with([
    'banner on' => [true],
    'banner off' => [false],
]);

test('an offer that ends before the plan is submitted opens the full price checkout', function () {
    completeWelcomeThroughSoftware($this->user);
    givePlanPrice(Plan::where('slug', 'mini')->firstOrFail(), Interval::Monthly, 'price_socials_monthly');
    $offer = Offer::factory()->create(['ends_at' => now()->addHour()]);
    $this->user->account->offers()->attach($offer, ['claimed_at' => now()]);

    $this->travel(2)->hours();

    $this->mock(StartSubscriptionCheckout::class)
        ->shouldReceive('redirect')
        ->once()
        ->withArgs(fn ($account, $priceId, $cancelUrl, $plan, $checkoutOffer = null): bool => blank($checkoutOffer))
        ->andReturn(redirect('https://checkout.stripe.test/session'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertRedirect('https://checkout.stripe.test/session');
});

test('the plan step sends countryBlocked for a blocked account', function () {
    completeWelcomeThroughSoftware($this->user);
    $this->user->account->update(['blocked_country_at' => now()]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('countryBlocked', true));
});

test('the plan step sends countryBlocked false for an allowed account', function () {
    completeWelcomeThroughSoftware($this->user);

    $this->actingAs($this->user->fresh())
        ->get(route('app.welcome.plan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('countryBlocked', false));
});

test('plan store refuses a blocked Owner country without opening checkout', function () {
    completeWelcomeThroughSoftware($this->user);
    $this->user->update(['country' => 'DE']);
    givePlanPrice(Plan::where('slug', 'mini')->firstOrFail(), Interval::Monthly, 'price_monthly_test');

    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->user->fresh())
        ->from(route('app.welcome.plan'))
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertRedirect(route('app.welcome.plan'))
        ->assertSessionHas('flash.error', __('rxdesk.country.blocked'));

    expect($this->user->fresh()->account->plan_id)->toBeNull();
});

test('plan store refuses an account whose completed checkout was billed to a blocked country', function () {
    completeWelcomeThroughSoftware($this->user);
    $this->user->account->update([
        'blocked_country' => 'GB',
        'blocked_country_at' => now(),
        'blocked_country_payment_status' => 'unpaid',
    ]);
    givePlanPrice(Plan::where('slug', 'mini')->firstOrFail(), Interval::Monthly, 'price_monthly_test');

    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->user->fresh())
        ->from(route('app.welcome.plan'))
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertRedirect(route('app.welcome.plan'))
        ->assertSessionHas('flash.error', __('rxdesk.country.blocked'));
});

test('a blocked account never reaches checkout', function () {
    completeWelcomeThroughSoftware($this->user);
    $this->user->update(['country' => 'DE']);

    $offer = Offer::factory()->trial(7)->create();
    $this->user->account->offers()->attach($offer, ['claimed_at' => now()]);
    givePlanPrice(Plan::where('slug', 'mini')->firstOrFail(), Interval::Monthly, 'price_monthly_test');

    $this->mock(StartSubscriptionCheckout::class)->shouldNotReceive('redirect');

    $this->actingAs($this->user->fresh())
        ->from(route('app.welcome.plan'))
        ->post(route('app.welcome.plan.store'), ['plan' => 'mini', 'interval' => 'monthly'])
        ->assertRedirect(route('app.welcome.plan'))
        ->assertSessionHas('flash.error', __('rxdesk.country.blocked'));

    expect($this->user->fresh()->account->trial_ends_at)->toBeNull();
});
