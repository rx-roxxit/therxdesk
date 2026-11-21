<?php

declare(strict_types=1);

use App\Enums\User\Goal;
use App\Enums\User\Persona;
use App\Enums\User\PreviousSoftware;
use App\Models\Offer;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * @return list<string>
 */
function welcomePlanTestIdsStartingWith(mixed $page, string $prefix): array
{
    return $page->script(<<<JS
        [...document.querySelectorAll('[data-testid^="{$prefix}"]')].map((element) => element.dataset.testid)
    JS);
}

/**
 * @return list<string>
 */
function welcomePlanFeatureIconClasses(mixed $page, string $labelTestId): array
{
    return $page->script(<<<JS
        [...(document.querySelector('[data-testid="{$labelTestId}"]')?.closest('li')?.querySelector('svg')?.classList ?? [])]
    JS);
}

/**
 * @return list<int>
 */
function welcomePlanFeatureIconWidths(mixed $page, string $listTestId): array
{
    return $page->script(<<<JS
        [...document.querySelectorAll('[data-testid="{$listTestId}"] ul > li > svg')].map((icon) => Math.round(icon.getBoundingClientRect().width))
    JS);
}

function welcomePlanLastFeatureLabelTestId(mixed $page, string $listTestId): ?string
{
    return $page->script(<<<JS
        [...document.querySelectorAll('[data-testid="{$listTestId}"] ul > li')].at(-1)?.querySelector('[data-testid^="plan-feature-label-"]')?.dataset.testid
    JS);
}

function welcomePlanScrollsOnPhone(mixed $page): bool
{
    return (bool) $page->script('document.documentElement.scrollHeight > window.innerHeight');
}

function welcomePlanFocus(mixed $page, string $testId): void
{
    $page->script(<<<JS
        document.querySelector('[data-testid="{$testId}"]').focus()
    JS);
}

function welcomePlanStallRequests(mixed $page): void
{
    $page->script(<<<'JS'
        XMLHttpRequest.prototype.send = function () {};
    JS);
}

/**
 * @return array{spinner: bool, disabled: bool}
 */
function waitForWelcomePlanButtonSpinner(mixed $page, string $testId): array
{
    return $page->script(<<<JS
        (async () => {
            const button = document.querySelector('[data-testid="{$testId}"]');
            for (let i = 0; i < 50; i++) {
                if (button.querySelector('[role="status"]')) return { spinner: true, disabled: button.disabled };
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
            return { spinner: false, disabled: button.disabled };
        })()
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

test('the plan step shows the free trial offer and what is included', function () {
    config(['trypost.self_hosted' => false]);

    $user = welcomeOwnerOnPlanStep();
    $user->account->offers()->attach(Offer::factory()->freeTrial()->create(), ['claimed_at' => now()]);
    $this->actingAs($user);

    $page = visit(route('app.welcome.plan'));

    waitFor($page, '@plan-card-mini');
    waitForWebFonts($page);
    waitFor($page, '@welcome-plan-continue');
    waitForWebFonts($page);

    $page->assertRoute('app.welcome.plan')
        ->assertSee('Activate your account')
        ->assertSee('Connect your channels and schedule your first post today.')
        ->assertMissing('@plan-card-standard')
        ->assertSeeIn('@plan-price-mini', 'Free')
        ->assertSeeIn('@plan-price-suffix-mini', 'for 7 days')
        ->assertSeeIn('@plan-price-original-mini', '$11.90/month')
        ->assertMissing('@plan-price-unit-mini')
        ->assertVisible('@plan-networks-info-mini')
        ->assertSeeIn('@welcome-plan-continue', 'Start free trial')
        ->assertSeeIn('@welcome-plan-terms', 'Then $11.90/month. Cancel anytime')
        ->assertMissing('@welcome-plan-offer')
        ->assertVisible('@welcome-step-plan')
        ->assertNoJavaScriptErrors();

    $features = array_map(
        fn (string $testId): string => Str::after($testId, 'plan-feature-label-mini-'),
        welcomePlanTestIdsStartingWith($page, 'plan-feature-label-mini-'),
    );

    expect(welcomePlanFeatureIconClasses($page, 'plan-feature-label-mini-analytics'))->toContain('tabler-icon-trending-up')
        ->and(welcomePlanFeatureIconClasses($page, 'plan-feature-label-mini-mcp'))->toContain('tabler-icon-settings-ai')
        ->and(welcomePlanFeatureIconWidths($page, 'plan-feature-list-mini'))->each->toBe(20)
        ->and($features)->toBe(['networks_all', 'accounts_unlimited', 'ideas', 'ai', 'calendar', 'analytics', 'mcp', 'repurpose', 'team']);

    $page->assertSeeIn('@plan-feature-label-mini-team', 'Unlimited team members')
        ->assertSeeIn('@plan-feature-label-mini-networks_all', 'All social networks included')
        ->assertSeeIn('@plan-feature-label-mini-analytics', 'Advanced analytics')
        ->assertSeeIn('@plan-feature-label-mini-ideas', 'Content planning')
        ->assertVisible('@plan-feature-info-mini-ideas');
});

test('the plan step shows the full price without an offer, even when a trial offer exists', function () {
    config(['trypost.self_hosted' => false]);
    Offer::factory()->freeTrial()->create();

    $this->actingAs(welcomeOwnerOnPlanStep());

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@welcome-plan-continue');
    waitForWebFonts($page);

    $page->assertSeeIn('@plan-price-mini', '$11.90')
        ->assertSeeIn('@plan-price-unit-mini', '/month')
        ->assertMissing('@plan-price-original-mini')
        ->assertMissing('@plan-price-suffix-mini')
        ->assertSeeIn('@welcome-plan-continue', 'Activate my account')
        ->assertSeeIn('@welcome-plan-terms', 'Cancel anytime')
        ->assertDontSee('7 days')
        ->assertNoJavaScriptErrors();

    expect(trim((string) $page->text('@plan-price-mini')))->toBe('$11.90')
        ->and(trim((string) $page->text('@welcome-plan-terms')))->toBe('Cancel anytime');
});

test('the plan step does not list the workspace limit, so team members close the list', function () {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(welcomeOwnerOnPlanStep());

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@plan-feature-list-mini');
    waitForWebFonts($page);

    expect(welcomePlanLastFeatureLabelTestId($page, 'plan-feature-list-mini'))->toBe('plan-feature-label-mini-team');

    $page->assertMissing('@plan-highlight-mini')
        ->assertMissing('@plan-workspaces-info-mini')
        ->assertNoJavaScriptErrors();
});

test('the plan step fits a phone without scrolling', function () {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(welcomeOwnerOnPlanStep());

    $page = visit(route('app.welcome.plan'))->resize(390, 844);
    waitFor($page, '@plan-card-mini');
    waitForWebFonts($page);

    expect(welcomePlanScrollsOnPhone($page))->toBeFalse();

    $page->assertVisible('@welcome-plan-continue')
        ->assertNoJavaScriptErrors();
});

test('the feature info opens on a tap or click', function (int $width, int $height) {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(welcomeOwnerOnPlanStep());

    $page = visit(route('app.welcome.plan'))->resize($width, $height);
    waitFor($page, '@plan-feature-info-mini-team');
    waitForWebFonts($page);

    $page->click('@plan-feature-info-mini-team');
    waitFor($page, '@plan-feature-info-mini-team');
    waitForWebFonts($page);

    waitFor($page, '[data-slot="popover-content"]');

    expect(trim((string) $page->text('[data-slot="popover-content"]')))->toStartWith('Invite as many people as you want');

    $page->click('@plan-networks-info-mini');
    waitFor($page, '@plan-networks-mini');
    waitForWebFonts($page);

    $page->assertVisible('@plan-networks-mini')
        ->assertNoJavaScriptErrors();
})->with([
    'desktop' => [1440, 900],
    'phone' => [390, 844],
]);

test('the feature info opens from the keyboard and closes with Escape', function () {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(welcomeOwnerOnPlanStep());

    $page = visit(route('app.welcome.plan'))->resize(1440, 900);
    waitFor($page, '@plan-feature-info-mini-team');
    waitForWebFonts($page);

    welcomePlanFocus($page, 'plan-feature-info-mini-team');
    $page->keys('@plan-feature-info-mini-team', 'Enter');

    waitFor($page, '[data-slot="popover-content"]');
    $page->assertVisible('[data-slot="popover-content"]');

    $page->keys('@plan-feature-info-mini-team', 'Escape');
    waitForGone($page, '[data-slot="popover-content"]');

    $page->assertMissing('[data-slot="popover-content"]');

    $page->assertNoJavaScriptErrors();
});

test('the activate button shows a spinner while checkout opens', function () {
    config(['trypost.self_hosted' => false]);

    $this->actingAs(welcomeOwnerOnPlanStep());

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@welcome-plan-continue');
    waitForWebFonts($page);

    welcomePlanStallRequests($page);
    $page->click('@welcome-plan-continue');

    expect(waitForWelcomePlanButtonSpinner($page, 'welcome-plan-continue'))->toBe(['spinner' => true, 'disabled' => true]);
});

test('the plan step shows an offer\'s banner and price', function (string $offer, string $name, string $headline, string $price, ?string $unit, string $period) {
    config(['trypost.self_hosted' => false]);

    $user = welcomeOwnerOnPlanStep();
    $user->account->offers()->attach(Offer::factory()->{$offer}()->withBanner()->create(), ['claimed_at' => now()]);
    $this->actingAs($user);

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@welcome-plan-offer');

    $page->assertSeeIn('@welcome-plan-offer-name', $name)
        ->assertSeeIn('@welcome-plan-offer-headline', $headline)
        ->assertSeeIn('@plan-price-original-mini', '$11.90/month')
        ->assertSeeIn('@plan-price-mini', $price)
        ->assertSeeIn('@plan-price-suffix-mini', $period)
        ->assertSeeIn('@welcome-plan-continue', 'Activate my account')
        ->assertSeeIn('@welcome-plan-terms', 'Then $11.90/month. Cancel anytime')
        ->assertNoJavaScriptErrors();

    if ($unit === null) {
        $page->assertMissing('@plan-price-unit-mini');
    } else {
        $page->assertSeeIn('@plan-price-unit-mini', $unit);
    }
})->with([
    'black friday shows its unit' => ['blackFriday', 'Black Friday', '50% off your first 3 months', '$9.50', '/month', 'for your first 3 months'],
    'first month has no unit' => ['firstMonth', 'First month', 'Your first month for $1', '$1', null, 'for your first month'],
]);

test('the plan step keeps an offer\'s price but hides its banner when the offer does not show one', function () {
    config(['trypost.self_hosted' => false]);

    $user = welcomeOwnerOnPlanStep();
    $user->account->offers()->attach(Offer::factory()->blackFriday()->create(['show_banner' => false]), ['claimed_at' => now()]);
    $this->actingAs($user);

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@plan-price-mini');

    $page->assertMissing('@welcome-plan-offer')
        ->assertSeeIn('@plan-price-mini', '$9.50')
        ->assertNoJavaScriptErrors();
});

test('the plan step shows neither a banner nor an offer price for an offer without copy', function () {
    config(['trypost.self_hosted' => false]);

    $user = welcomeOwnerOnPlanStep();
    $user->account->offers()->attach(Offer::factory()->create(), ['claimed_at' => now()]);
    $this->actingAs($user);

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@plan-price-mini');

    $page->assertMissing('@welcome-plan-offer')
        ->assertSeeIn('@plan-price-unit-mini', '/month')
        ->assertMissing('@plan-price-original-mini')
        ->assertDontSee('offers.')
        ->assertNoJavaScriptErrors();

    expect(trim((string) $page->text('@plan-price-mini')))->toBe('$11.90');
});

test('a trial without a card says so under the button', function () {
    config(['trypost.self_hosted' => false]);

    $user = welcomeOwnerOnPlanStep();
    $user->account->offers()->attach(Offer::factory()->freeTrial()->withoutCard()->create(['slug' => 'ft7dnc']), ['claimed_at' => now()]);
    $this->actingAs($user);

    $page = visit(route('app.welcome.plan'));
    waitFor($page, '@welcome-plan-terms');

    $page->assertSeeIn('@plan-price-mini', 'Free')
        ->assertSeeIn('@welcome-plan-continue', 'Start free trial')
        ->assertSeeIn('@welcome-plan-terms', 'No credit card required')
        ->assertDontSee('Cancel anytime')
        ->assertDontSee('Then $11.90/month')
        ->assertNoJavaScriptErrors();
});
