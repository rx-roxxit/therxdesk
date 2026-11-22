<?php

declare(strict_types=1);

use App\Enums\User\Goal;
use App\Enums\User\Persona;
use App\Enums\User\PreviousSoftware;
use App\Models\Offer;
use App\Models\User;
use App\Models\Workspace;

/**
 * @return list<string>
 */
function welcomePlanTestIdsStartingWith(mixed $page, string $prefix): array
{
    return $page->script(<<<JS
        [...document.querySelectorAll('[data-testid^="{$prefix}"]')].map((element) => element.dataset.testid)
    JS);
}

function welcomeOwnerOnPlanStep(): User
{
    $user = User::factory()->create();
    $user->update([
        'persona' => Persona::Agency->value,
        'goals' => [Goal::SaveTime->value],
        'previous_software' => PreviousSoftware::Buffer->value,
    ]);

    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

test('the plan step shows four plans in catalog order with prices, limits and the trial', function () {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(welcomeOwnerOnPlanStep());

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@plan-card-mini');
    waitForWebFonts($page);

    expect(welcomePlanTestIdsStartingWith($page, 'plan-card-'))->toBe([
        'plan-card-mini',
        'plan-card-standard',
        'plan-card-plus',
        'plan-card-max',
    ]);

    expect($page->script(
        "document.querySelector('[data-testid=\"plan-card-mini\"]')?.dataset.selected ?? null",
    ))->toBe('true');

    $page->assertSee('Choose your plan')
        ->assertSee('Pay monthly or yearly. Change or cancel anytime.')
        ->assertSeeIn('@plan-price-mini', '$11.90')
        ->assertSeeIn('@plan-price-unit-mini', '/month')
        ->assertSeeIn('@plan-price-plus', '$29.90')
        ->assertSeeIn('@plan-storage-mini', '2 GB storage')
        ->assertSeeIn('@plan-ai-credits-mini', '2,500 AI credits a month')
        ->assertSeeIn('@plan-unlimited-mini', 'Unlimited workspaces, channels, posts and members.')
        ->assertSeeIn('@plan-select-mini', 'Start 7-day free trial')
        ->assertSeeIn('@plan-terms-mini', 'Then $11.90/month from')
        ->assertNoJavaScriptErrors();
});

test('the switch shows the yearly price of every plan', function () {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(welcomeOwnerOnPlanStep());

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@plan-interval-yearly');
    waitForWebFonts($page);

    $page->click('@plan-interval-yearly');
    waitForState($page, '@plan-interval-yearly', "element.getAttribute('aria-pressed') === 'true'");

    $page->assertSeeIn('@plan-price-plus', '$299.00')
        ->assertSeeIn('@plan-price-unit-plus', '/year')
        ->assertSeeIn('@plan-terms-plus', 'Then $299.00/year from')
        ->assertNoJavaScriptErrors();
});

test('an owner who already had a trial sees Subscribe instead', function () {
    config(['trypost.self_hosted' => false]);

    $user = welcomeOwnerOnPlanStep();
    $user->account->update(['trial_ends_at' => now()->subDay()]);
    $this->actingAs($user->fresh());

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@plan-select-mini');
    waitForWebFonts($page);

    $page->assertSeeIn('@plan-select-mini', 'Subscribe')
        ->assertSeeIn('@plan-terms-mini', "You'll be charged $11.90/month today. Cancel anytime.")
        ->assertDontSee('free trial')
        ->assertNoJavaScriptErrors();
});

test('pressing a plan before verifying the email opens the verify dialog', function () {
    config(['trypost.self_hosted' => false]);

    $user = welcomeOwnerOnPlanStep();
    $user->update(['email_verified_at' => null]);
    $this->actingAs($user->fresh());

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@plan-select-plus');
    waitForWebFonts($page);

    $page->click('@plan-select-plus');
    waitForOpen($page, '@verify-email-dialog');

    $page->assertSeeIn('@verify-email-dialog', 'Verify your email')
        ->assertSeeIn('@verify-email-dialog', $user->email)
        ->assertVisible('@verify-email-resend')
        ->assertVisible('@verify-email-close');

    $page->click('@verify-email-close');
    waitForGone($page, '@verify-email-dialog');

    $page->assertMissing('@verify-email-dialog')
        ->assertRoute('app.welcome.plan')
        ->assertNoJavaScriptErrors();
});

test('a trial without a card uses the offer copy on every card', function () {
    config(['trypost.self_hosted' => false]);

    $user = welcomeOwnerOnPlanStep();
    $user->account->offers()->attach(
        Offer::factory()->freeTrial()->withoutCard()->create(['slug' => 'ft7dnc']),
        ['claimed_at' => now()],
    );
    $this->actingAs($user->fresh());

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@plan-select-mini');
    waitForWebFonts($page);

    $page->assertSeeIn('@plan-select-mini', 'Start free trial')
        ->assertSeeIn('@plan-terms-mini', 'No credit card required')
        ->assertNoJavaScriptErrors();
});

test('the offer banner stays above the four cards', function () {
    config(['trypost.self_hosted' => false]);

    $user = welcomeOwnerOnPlanStep();
    $user->account->offers()->attach(
        Offer::factory()->blackFriday()->withBanner()->create(),
        ['claimed_at' => now()],
    );
    $this->actingAs($user->fresh());

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@welcome-plan-offer');
    waitForWebFonts($page);

    $page->assertSeeIn('@welcome-plan-offer-name', 'Black Friday')
        ->assertSeeIn('@welcome-plan-offer-headline', '50% off your first 3 months')
        ->assertSeeIn('@plan-price-mini', '$11.90')
        ->assertNoJavaScriptErrors();
});

test('the plan cards use four, two and one column at each width', function (int $width, int $columns) {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(welcomeOwnerOnPlanStep());

    $page = visit(route('app.welcome.plan'))->resize($width, 900);
    waitFor($page, '@plan-card-mini');
    waitForWebFonts($page);

    $lefts = $page->script(
        "[...document.querySelectorAll('[data-testid^=\"plan-card-\"]')].map((card) => Math.round(card.getBoundingClientRect().left))",
    );

    expect(count(array_unique($lefts)))->toBe($columns);

    $page->assertNoJavaScriptErrors();
})->with([
    'desktop' => [1440, 4],
    'tablet' => [1024, 2],
    'phone' => [390, 1],
]);
