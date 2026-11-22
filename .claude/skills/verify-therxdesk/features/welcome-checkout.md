# Welcome and checkout

A new Owner answers the Welcome steps, sees the Plan step, pays on Stripe Checkout (on a Trial), waits on Processing
until the webhook gives the Account access, and lands on the Schedule.

## Sub-features

- Welcome steps: persona (`@welcome-persona-<persona>`, `@welcome-persona-continue`), goals (`@welcome-goal-<goal>`,
  several, `@welcome-goals-continue`), current tool (`@welcome-software-<value>`, `@welcome-software-continue`).
- Plan step (`/welcome/plan`): "Choose your plan" and the Monthly/Yearly switch (`@plan-interval-monthly`,
  `@plan-interval-yearly`, `@plan-save-two-months`), then four cards in Catalog order (`@plan-card-<slug>`):
  Mini, Standard, Plus and Max (one column on phones, two from `sm`, four from 1081px). Each card shows the price for
  the picked interval (`@plan-price-<slug>`, `@plan-price-unit-<slug>`), its storage
  (`@plan-storage-<slug>`), its monthly AI credits (`@plan-ai-credits-<slug>`), the unlimited line
  (`@plan-unlimited-<slug>`), its button (`@plan-select-<slug>`) and the terms under it (`@plan-terms-<slug>`).
  Prices come from `plan_prices` (see `selling-mode.md`), labels from the Plan row, and the Trial length from the
  Catalog (`catalog_settings.trial_days`).
- An Owner who never had a Trial sees "Start 7-day free trial" and "Then $11.90/month from <date>. Cancel anytime.
  One free trial per card."; one who had one sees "Subscribe". A no-card trial Offer uses its own `checkout_button`
  and `terms`.
- `/register?plan=<slug>&interval=monthly|yearly` is kept for the guest and preselects that card and interval; a
  junk value is dropped silently. The preselected card carries `data-selected="true"`.
- Pressing a card button before the email is verified opens `@verify-email-dialog` with `@verify-email-close`
  ("Not now") and `@verify-email-resend`, which sends the existing verification email (Mailpit). The link returns to
  `/welcome/plan`.
- An Offer (`?offer=`) changes the step: a banner (`@welcome-plan-offer`), a trial, or a no-card trial that skips
  Checkout. The fixture has no Offer. `OfferSeeder` has local rows, but its coupons are Stripe test-mode objects this
  sandbox does not guarantee, so the journeys always take the full-price path.
- Stripe Checkout (hosted, sandbox): the Owner's language (never `auto`), a required billing address, a required
  Terms consent checkbox, no promotion code field, and `subscription_data.trial_end` on a first checkout.
- Processing (`/billing/processing`) polls until the Account has access, then opens the Schedule.
- The webhooks: Cashier's handlers write the `subscriptions` row, and `customer.subscription.created` / `updated`
  writes the Account's Plan (`accounts.plan_id`).

## How to get to it (user POV)

In selling mode, sign in as a new Owner: Welcome opens. Pick a persona, a goal, a current tool, then press a card's
button on the Plan step. A guest can also arrive with `/register?plan=plus&interval=yearly` already on that card. On
Stripe's page tick Terms, fill the billing name and address, type the test card and press "Start trial".

## Driving it with Playwright

- Journeys: `$V/drive.sh welcome-plan` (registers a guest through `/register?plan=plus&interval=yearly`, completes
  Welcome, checks the four cards and the switch, the verify dialog and Mailpit, then pays) and
  `$V/drive.sh welcome-checkout` (the fixture Owner, Mini monthly). `welcome-plan` makes its own guest. `welcome-checkout`
  needs `fixture.sh up` and a fixture Owner who has not answered Welcome: a second run lands on the Schedule and times
  out waiting for the persona step.
- Stripe's page: `payOnStripeCheckout()` in `drive.ts` ticks the Terms consent checkbox when the session shows one,
  then fills card `4242 4242 4242 4242` with an expiry two years ahead, CVC `123` and a name in the Elements frame.
  When the session collects a billing address, it fills the billing name, address line 1, city and postal code in the
  Checkout frame, then presses `[data-testid="payment-form-submit-button"]`. The billing country follows the IP
  (Thailand here); a US card form adds `#payment-postalCodeInput`, which the helper fills when shown.
- Mailpit: `welcome-plan` waits for the signup email through Mailpit's HTTP API (`VERIFY_MAILPIT_URL`, default
  `http://localhost:8025`), clicks Resend, waits for a different message, and opens its `/verify-email/...` link.
- End state: the path is `/schedule...` and `auth.hasActiveSubscription` is `true`; `auth.plan.name` is `Mini` for
  `welcome-checkout` (`08-after-checkout.png`) and `Plus` for `welcome-plan` (`10-after-checkout.png`). The
  subscription is `trialing` for the Catalog's trial days, not `active`.
- Side effects, from the host:
  `$V/db.sh "select p.slug, s.stripe_status, s.stripe_price from accounts a left join plans p on p.id = a.plan_id left join subscriptions s on s.account_id = a.id where a.id = '<accountId>'"`
  shows `mini | trialing | <the Mini monthly Price id in plan_prices>` after `welcome-checkout`, and
  `plus | trialing | <the Plus yearly Price id>` after `welcome-plan`. `$V/selling.sh logs` shows every event
  answered `<-- [200]` (a wrong signing secret answers 403).

## Gotchas

- Stripe's page loads in about 5 to 15 seconds and its card form in a second iframe: the helper waits up to 60.
- The Terms consent checkbox belongs to the session (`consent_collection.terms_of_service`), not to one billing
  journey; `payOnStripeCheckout` ticks it whenever it is on screen.
- An hCaptcha frame loads with the page; with the test card it has not challenged so far. If it starts to, the
  journey cannot pass it: report it.
- Each run leaves a sandbox Customer; `fixture.sh up` and `cleanup.sh` delete the fixture's, and
  `fixture.sh guests` deletes the guests' (which cancels their subscriptions).
