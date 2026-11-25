<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Enums\Billing\Interval;
use App\Enums\PostHog\WelcomeEvent;
use App\Enums\User\Goal;
use App\Enums\User\Persona;
use App\Enums\User\PreviousSoftware;
use App\Http\Middleware\App\CapturePlan;
use App\Http\Requests\App\Welcome\StoreWelcomeGoalsRequest;
use App\Http\Requests\App\Welcome\StoreWelcomePersonaRequest;
use App\Http\Requests\App\Welcome\StoreWelcomePlanRequest;
use App\Http\Requests\App\Welcome\StoreWelcomeSoftwareRequest;
use App\Http\Resources\App\OfferResource;
use App\Http\Resources\App\WelcomePlanResource;
use App\Models\Account;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\User;
use App\Services\PostHogService;
use App\Support\Billing\BillingPortal;
use App\Support\Billing\BlockedCountries;
use App\Support\Billing\ConfigureSubscriptionCheckout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class WelcomeController extends Controller
{
    public function persona(Request $request): InertiaResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfUnavailable($request)) {
            return $redirect;
        }

        $user = $request->user();

        return Inertia::render('welcome/Persona', [
            'personas' => array_map(fn (Persona $persona): string => $persona->value, Persona::cases()),
            'selected' => $user->persona?->value,
        ]);
    }

    public function storePersona(StoreWelcomePersonaRequest $request, PostHogService $postHog): RedirectResponse
    {
        if ($redirect = $this->redirectIfUnavailable($request)) {
            return $redirect;
        }

        $user = $request->user();
        $persona = (string) $request->validated('persona');

        $user->update(['persona' => $persona]);

        $postHog->identify($user->id, [
            'persona' => $persona,
        ]);
        $postHog->capture(
            $user->id,
            WelcomeEvent::Persona->value,
            ['persona' => $persona],
            $user->account,
        );

        return redirect()->route('app.welcome.goals');
    }

    public function goals(Request $request): InertiaResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request)) {
            return $redirect;
        }

        $user = $request->user();

        return Inertia::render('welcome/Goals', [
            'goals' => array_map(fn (Goal $goal): string => $goal->value, Goal::cases()),
            'selected' => $user->goals ?? [],
        ]);
    }

    public function storeGoals(StoreWelcomeGoalsRequest $request, PostHogService $postHog): RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request)) {
            return $redirect;
        }

        $user = $request->user();
        $goals = array_values($request->validated('goals'));

        $user->update(['goals' => $goals]);

        $postHog->identify($user->id, [
            'goals' => $goals,
        ]);
        $postHog->capture(
            $user->id,
            WelcomeEvent::Goals->value,
            ['goals' => $goals],
            $user->account,
        );

        return redirect()->route('app.welcome.software');
    }

    public function software(Request $request): InertiaResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request, requireGoals: true)) {
            return $redirect;
        }

        $user = $request->user();

        return Inertia::render('welcome/Software', [
            'software' => array_map(fn (PreviousSoftware $software): array => [
                'value' => $software->value,
                'name' => $software->brandName(),
            ], PreviousSoftware::cases()),
            'selected' => $user->previous_software?->value,
        ]);
    }

    public function storeSoftware(StoreWelcomeSoftwareRequest $request, PostHogService $postHog): RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request, requireGoals: true)) {
            return $redirect;
        }

        $user = $request->user();
        $previousSoftware = (string) $request->validated('previous_software');

        $user->update(['previous_software' => $previousSoftware]);

        $postHog->identify($user->id, [
            'previous_software' => $previousSoftware,
        ]);
        $postHog->capture(
            $user->id,
            WelcomeEvent::Software->value,
            ['previous_software' => $previousSoftware],
            $user->account,
        );

        return redirect()->route('app.welcome.plan');
    }

    public function plan(Request $request): InertiaResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request, requireGoals: true, requireSoftware: true)) {
            return $redirect;
        }

        $account = $request->user()->account;

        // A Complimentary Plan in force has app access, but its plan step is
        // the state the Owner should see: the Plan, its end date, and the way
        // to the app. It is not skipped like a Subscription.
        if ($account?->isComplimentary() && $request->user()->isAccountOwner()) {
            return Inertia::render('welcome/Plan', [
                'plans' => [],
                'selected' => [
                    'plan' => (string) $account->plan?->slug,
                    'interval' => Interval::Monthly->value,
                ],
                'offer' => null,
                'trial' => null,
                'retry' => null,
                'countryBlocked' => filled($account->blocked_country_at),
                'lapsedDeletion' => null,
                'complimentary' => [
                    'plan_name' => $account->plan?->name,
                    'ends_at' => $account->complimentaryEndsAt()?->toIso8601String(),
                ],
            ]);
        }

        $plans = Plan::query()
            ->active()
            ->with('prices')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        abort_if($plans->isEmpty(), Response::HTTP_NOT_FOUND);

        $offer = $account->currentOffer();

        return Inertia::render('welcome/Plan', [
            'plans' => $plans
                ->map(fn (Plan $plan): array => WelcomePlanResource::make($plan, $account, $offer)->resolve($request))
                ->all(),
            'selected' => [
                'plan' => $this->selectedPlan($request, $plans)->slug,
                'interval' => $this->selectedInterval($request)->value,
            ],
            'offer' => filled($offer) ? OfferResource::make($offer)->resolve($request) : null,
            'trial' => $this->trial($account, null),
            'retry' => $this->retry($account),
            'countryBlocked' => filled($account->blocked_country_at),
            'lapsedDeletion' => $this->lapsedDeletion($account),
            'complimentary' => null,
        ]);
    }

    /**
     * The Owner retries the first charge of a Trial at Stripe's billing portal:
     * the same step, with the card form, and back to the plan when done.
     */
    public function planPortal(Request $request): RedirectResponse
    {
        if ($request->user()->account?->isSuspended()) {
            return redirect()->route('app.welcome.suspended');
        }

        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        abort_unless($request->user()->isAccountOwner(), Response::HTTP_FORBIDDEN);

        if ($request->user()->accountOrFail()->isComplimentary()) {
            return redirect()->route('app.welcome.plan');
        }

        return BillingPortal::redirect($request->user()->accountOrFail(), route('app.welcome.plan'));
    }

    public function storePlan(
        StoreWelcomePlanRequest $request,
        StartSubscriptionCheckout $checkout,
    ): Response|RedirectResponse {
        if ($redirect = $this->redirectIfStepIncomplete($request, requireGoals: true, requireSoftware: true)) {
            return $redirect;
        }

        $user = $request->user();

        // The System admin ends the Complimentary Plan before the Account can
        // subscribe: Checkout is not the way out of this state.
        if ($user->accountOrFail()->isComplimentary()) {
            return back()->with('flash.error', __('rxdesk.complimentary.checkout_blocked'));
        }

        abort_if(blank($user->email_verified_at), Response::HTTP_FORBIDDEN);

        if ($this->countryBlocked($user->accountOrFail())) {
            return back()->with('flash.error', __('rxdesk.country.blocked'));
        }

        // The first charge of a Trial is being retried at Stripe: a second
        // checkout here would open a second subscription. The step already
        // shows the Update card state for it.
        if ($user->accountOrFail()->hasPendingFirstCharge()) {
            return redirect()->route('app.welcome.plan');
        }

        $interval = Interval::from((string) $request->validated('interval'));
        $plan = Plan::query()
            ->active()
            ->with('prices')
            ->get()
            ->firstWhere('slug', (string) $request->validated('plan'));

        abort_if(blank($plan), Response::HTTP_NOT_FOUND);

        $request->session()->put(CapturePlan::PLAN_SESSION_KEY, $plan->slug);
        $request->session()->put(CapturePlan::INTERVAL_SESSION_KEY, $interval->value);

        $priceId = $plan->priceFor($interval)?->stripe_price_id;

        abort_if(blank($priceId), Response::HTTP_INTERNAL_SERVER_ERROR, 'Price is not configured.');

        $offer = $user->account->currentOffer();

        return $checkout->redirect($user->account, $priceId, route('app.welcome.plan'), $plan, $offer);
    }

    public function subscriptionRequired(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user->account?->isSuspended()) {
            return redirect()->route('app.welcome.suspended');
        }

        if ($user->account?->hasAppAccess()) {
            return redirect()->route('app.calendar');
        }

        if ($user->isAccountOwner()) {
            return redirect()->route('app.welcome.plan');
        }

        return Inertia::render('welcome/SubscriptionRequired', [
            'ownerName' => $user->account?->owner?->name,
        ]);
    }

    /**
     * The holding screen for every member of a Suspended Account. It leaves on
     * its own once the System admin lifts the Suspension.
     */
    public function suspended(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user->account?->isSuspended()) {
            return $this->whereTheyBelong($user);
        }

        return Inertia::render('welcome/Suspended');
    }

    /**
     * The Plan the cards preselect: the one kept from `/register`, else the
     * first in Catalog order. A stored slug that no longer names an active
     * Plan is dropped.
     *
     * @param  Collection<int, Plan>  $plans
     */
    private function selectedPlan(Request $request, Collection $plans): Plan
    {
        $stored = $request->session()->get(CapturePlan::PLAN_SESSION_KEY);

        if (is_string($stored) && filled($stored)) {
            $plan = $plans->firstWhere('slug', $stored);

            if (filled($plan)) {
                return $plan;
            }
        }

        if (filled($stored)) {
            $request->session()->forget(CapturePlan::PLAN_SESSION_KEY);
        }

        return $plans->first();
    }

    /**
     * The interval the switch preselects: the one kept from `/register`, else
     * monthly. A stored value that is no longer an Interval is dropped.
     */
    private function selectedInterval(Request $request): Interval
    {
        $stored = $request->session()->get(CapturePlan::INTERVAL_SESSION_KEY);

        if (is_string($stored) && filled($stored)) {
            $interval = Interval::tryFrom($stored);

            if ($interval !== null) {
                return $interval;
            }
        }

        if (filled($stored)) {
            $request->session()->forget(CapturePlan::INTERVAL_SESSION_KEY);
        }

        return Interval::Monthly;
    }

    /**
     * The trial the step promises, or null when the Account already had one.
     *
     * @return array{days: int, first_charge_at: string}|null
     */
    private function trial(Account $account, ?Offer $offer): ?array
    {
        $days = ConfigureSubscriptionCheckout::trialDays($offer, $account);

        if ($days <= 0) {
            return null;
        }

        return [
            'days' => $days,
            'first_charge_at' => now()->addDays($days)->toIso8601String(),
        ];
    }

    /**
     * The retry step after a Trial's first charge failed: the plan the Account
     * is on, and where it updates the card. Null in every other state.
     *
     * @return array{plan: string|null, update_card_url: string}|null
     */
    private function retry(Account $account): ?array
    {
        if (! $account->hasPendingFirstCharge()) {
            return null;
        }

        return [
            'plan' => $account->plan?->name,
            'update_card_url' => route('app.welcome.plan.portal'),
        ];
    }

    /**
     * The date a Lapsed Owner's data is deleted, shown on the plan step. A
     * never-paid signup is not Lapsed and gets no such line.
     *
     * @return array{date: string}|null
     */
    private function lapsedDeletion(Account $account): ?array
    {
        if (! $account->isLapsed()) {
            return null;
        }

        if (blank($account->deletion_scheduled_at)) {
            return null;
        }

        return [
            'date' => $account->deletion_scheduled_at->toIso8601String(),
        ];
    }

    private function countryBlocked(Account $account): bool
    {
        if (filled($account->blocked_country_at)) {
            return true;
        }

        return BlockedCountries::isBlocked($account->owner?->country);
    }

    private function redirectIfStepIncomplete(
        Request $request,
        bool $requireGoals = false,
        bool $requireSoftware = false,
    ): ?RedirectResponse {
        if ($redirect = $this->redirectIfUnavailable($request)) {
            return $redirect;
        }

        $user = $request->user();

        if (blank($user->persona)) {
            return redirect()->route('app.welcome.persona');
        }

        if ($requireGoals && ! Goal::containsCurrent($user->goals)) {
            return redirect()->route('app.welcome.goals');
        }

        if ($requireSoftware && blank($user->previous_software)) {
            return redirect()->route('app.welcome.software');
        }

        return null;
    }

    private function redirectIfUnavailable(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        if ($user->account?->isSuspended()) {
            return redirect()->route('app.welcome.suspended');
        }

        // A Complimentary Plan in force is app access, but its Owner keeps the
        // plan step to read it; every other owner with access goes on.
        if ($user->account?->isComplimentary() && $user->isAccountOwner()) {
            return null;
        }

        // Match EnsureAccountReady — generic-trial (no-card) users already have
        // app access and must not be sent through Stripe checkout again.
        // Self-hosted always has app access, so welcome/checkout is skipped too.
        if ($user->account?->hasAppAccess()) {
            return redirect()->route('app.calendar');
        }

        // Members can't check out — hold them on a dedicated screen instead of
        // walking an ICP flow they can never finish.
        if (! $user->isAccountOwner()) {
            return redirect()->route('app.welcome.subscription-required');
        }

        return null;
    }

    /**
     * Where a signed-in user goes when the Suspended screen has nothing to
     * hold: the app with access, the plan step for the Owner, the member hold
     * screen otherwise.
     */
    private function whereTheyBelong(User $user): RedirectResponse
    {
        if ($user->account?->hasAppAccess()) {
            return redirect()->route('app.calendar');
        }

        if ($user->isAccountOwner()) {
            return redirect()->route('app.welcome.plan');
        }

        return redirect()->route('app.welcome.subscription-required');
    }
}
