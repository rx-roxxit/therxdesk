# Billing

The Owner sees the Account's Plan, price and status, changes Plan or interval in a dialog, sees the cards and
invoices, and manages the subscription on Stripe's Customer Portal.

## Sub-features

- Entry: account menu (`@sidebar-workspace-menu`) → `@sidebar-menu-billing` ("Plans and billing"), or Settings →
  `@settings-nav-billing`. Owner only (any non-owner, a member or a non-owner admin, gets 403); selling mode only.
- Plan tab: current plan (`@billing-current-plan-name`, `-price`, `-badge`, `-status` for a trial, cancel or past
  due, `-discount`), "Change plan" (`@billing-change-plan`), "Subscribe" (`@billing-subscribe`, a generic trial).
- Plan dialog (`@billing-plan-dialog`, `PlanPicker`): Monthly / Yearly (`@plan-interval-<interval>`,
  `@plan-save-two-months`), a card per Plan (`@plan-card-<slug>`, `@plan-price-<slug>`, `@plan-select-<slug>`).
  Choosing a Plan swaps the subscription's price at once (proration charged to the card); without a subscription
  it opens Checkout.
- Payment methods (`@billing-payment-methods`, `@billing-card-<id>`), "Manage on Stripe" (`@billing-manage-stripe`,
  the Customer Portal).
- Invoices tab (`@billing-tab-invoices`, `@billing-invoices`, `@billing-invoice-download-<id>`, empty state
  `@billing-invoices-empty`); `/settings/account/billing?tab=invoices` deep-links to it.

## How to get to it (user POV)

In selling mode, with a subscribed Owner: open the account menu, choose "Plans and billing".

## Driving it with Playwright

- Journey: `$V/drive.sh billing`, right after `welcome-checkout` on the same fixture (it needs the Mini monthly
  subscription that journey makes; do not run `fixture.sh up` in between).
- Checks: Mini, `$11.90`, Free trial (after `welcome-checkout` the first checkout is on the Catalog trial; the
  journey also accepts Active for a subscription past its trial, and for a repeat card that skipped the Trial); the
  `4242` card listed; the dialog's Yearly shows `$9.92` a month; choosing Standard (`@plan-select-standard`) makes it
  the current plan at `$17.90`; "Manage on Stripe" opens `billing.stripe.com` on the Standard subscription at
  `$17.90 per month`.
- Side effect: the same `db.sh` query as `welcome-checkout` shows `standard | trialing | <the Standard monthly
  Price id in plan_prices>` right after the run (the swap keeps the trial); a repeat card shows `active` instead.

## Gotchas

- The dialog fades in: the journey waits for `document.getAnimations()` to finish before its screenshot.
- The upgrade changes the fixture's subscription for good; run `welcome-checkout` from a fresh fixture to repeat
  (`fixture.sh up` clears the Account's `trial_cards`, so the `4242` card gets a Trial again).
- The Customer Portal is the app's own configuration, created and updated through the Stripe API by
  `BillingPortalConfiguration::ensure()` (its id lives on `catalog_settings`), not the Dashboard default: plan
  switching off; card update, invoices, cancel at period end and the cancellation-reason question on. A test-to-live
  key change leaves the stored id unreachable, so `ensure()` recreates the configuration on the next open; cancelling
  there is not driven.
- The past-due state is its own journey: a paid-before subscription whose renewal failed keeps access and shows the
  sidebar banner and the `Past due` badge (`$V/drive.sh past-due`, see `trial-and-payment.md`). A lapse (Smart Retries
  exhausted, then `canceled`) is `$V/drive.sh lapsed` there too.
- Not driven yet: the Invoices tab, Subscribe on a generic trial, a downgrade, a yearly swap (which drops the
  discount).
