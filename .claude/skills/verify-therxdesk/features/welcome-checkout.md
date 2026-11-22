# Welcome and checkout

A new Owner answers the Welcome steps, sees the Plan, pays on Stripe Checkout, waits on Processing until the webhook
gives the Account access, and lands on the Schedule.

## Sub-features

- Welcome steps: persona (`@welcome-persona-<persona>`, `@welcome-persona-continue`), goals (`@welcome-goal-<goal>`,
  several, `@welcome-goals-continue`), current tool (`@welcome-software-<value>`, `@welcome-software-continue`).
- Plan step (`/welcome/plan`): the Mini card (`@plan-card-mini`, `@plan-price-mini`), terms,
  `@welcome-plan-continue`, which opens Stripe Checkout for Mini monthly. Welcome shows one Plan; the other three
  (Standard, Plus, Max) are on billing's change-plan picker. Prices come from `plan_prices` (see
  `selling-mode.md`).
- An Offer changes the Plan step: a banner (`@welcome-plan-offer`), a discounted price, a trial, or a no-card trial
  that skips Checkout. The fixture has no Offer; `OfferSeeder` has local ones, but its coupons are upstream's and
  are not in this sandbox.
- Stripe Checkout (hosted, sandbox): promotion code field, card form in a Stripe Elements iframe, Link opt-in.
- Processing (`/billing/processing`) polls until the Account has access, then opens the Schedule.
- The webhooks (`checkout.session.completed`, `customer.subscription.created`, `invoice.paid`, ...) write the
  `subscriptions` row and the Account's Plan.

## How to get to it (user POV)

In selling mode, sign in as a new Owner: Welcome opens. Pick a persona, a goal, a current tool, then "Continue" on
the Plan step. On Stripe's page type the test card and press "Subscribe".

## Driving it with Playwright

- Journey: `$V/drive.sh welcome-checkout`, after `fixture.sh up` (the journey needs an Owner who has not answered
  Welcome; a second run on the same fixture lands on the Plan step and fails).
- Stripe's page: `payOnStripeCheckout()` in `drive.ts` finds the Elements frame holding `#payment-numberInput` and
  fills card `4242 4242 4242 4242`, an expiry two years ahead, CVC `123` and a name, then presses
  `[data-testid="payment-form-submit-button"]`. The billing country follows the IP (Thailand here); a US country
  adds `#payment-postalCodeInput`, which the helper fills when shown.
- End state: the path is `/schedule...`, `auth.hasActiveSubscription` is `true`, `auth.plan.name` is `Mini`
  (`08-after-checkout.png`).
- Side effects, from the host:
  `$V/db.sh "select p.slug, s.stripe_status, s.stripe_price from accounts a left join plans p on p.id = a.plan_id left join subscriptions s on s.account_id = a.id where a.id = '<accountId>'"`
  shows `mini | active | <the Mini monthly Price id in plan_prices>`. `$V/selling.sh logs` shows every event
  answered `<-- [200]` (a wrong signing secret answers 403).

## Gotchas

- Stripe's page loads in about 5 to 15 seconds and its card form in a second iframe: the helper waits up to 60.
- An hCaptcha frame loads with the page; with the test card it has not challenged so far. If it starts to, the
  journey cannot pass it: report it.
- Each run leaves a sandbox Customer; `fixture.sh up` and `cleanup.sh` delete it (which cancels its subscription).
