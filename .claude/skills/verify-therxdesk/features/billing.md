# Billing

The Owner sees the Account's Plan, price and status, changes Plan or interval in a dialog, sees the cards and
invoices, and manages the subscription on Stripe's Customer Portal.

## Sub-features

- Entry: account menu (`@sidebar-workspace-menu`) → `@sidebar-menu-billing` ("Plans and billing"), or Settings →
  `@settings-nav-billing`. Owner only (any non-owner, a member or a non-owner admin, gets 403); selling mode only.
- Plan tab: current plan (`@billing-current-plan-name`, `-price`, `-badge`, `-discount`), the Account balance in
  credit (`@billing-account-balance`, only when Stripe holds a credit), "Change plan" (`@billing-change-plan`),
  "Subscribe" (`@billing-subscribe`: an Account with no subscription and no Complimentary Plan opens Checkout on
  the monthly price charged at once; it does not start a new Trial, including during a generic Trial).
- Status strip (`@billing-status-strip`, line `@billing-current-plan-status`) renders from the Account's state:
  "Trial until <date>, then $X a month" (`@billing-current-plan-trial-icon`), "Renews on <date> · $X a month",
  "Cancels on <date>", or a past-due strip showing only "Update your payment method to keep your subscription
  active." (`billing.past_due_notice.description`) with `@billing-update-payment`; the "Payment past due" title is
  the sidebar banner, and the badge reads "Past due". The renewal date comes from Stripe's
  current period (`renewsAt`), so an active Plan shows no strip when there is no Stripe customer to read.
- Usage (anchor `#usage`): Storage used of Quota (`@billing-usage-storage`,
  `@billing-usage-storage-line`) and AI credits used of total with their renewal date in the Owner's zone
  (`@billing-usage-ai`, `@billing-usage-ai-line`).
- Plan dialog (`@billing-plan-dialog`, `PlanPicker`): Monthly / Yearly (`@plan-interval-<interval>`,
  `@plan-save-two-months`), a card per Plan (`@plan-card-<slug>`, `@plan-price-<slug>`, `@plan-select-<slug>`).
  Choosing a Plan opens the confirmation dialog instead of posting.
- Confirmation dialog (`@billing-confirm-dialog`): the new price (`@billing-confirm-price`), when it is charged
  (`@billing-confirm-timing`: the prorated difference today, the Account balance after a downgrade, or nothing during
  a Trial), whether the Offer stays (`@billing-confirm-offer`), an upload pause over the new Quota
  (`@billing-confirm-quota`) and an AI pause when this month's use exceeds the new credits (`@billing-confirm-ai`),
  each only when it applies. Cancel (`@billing-confirm-cancel`) goes back to the picker; Change plan
  (`@billing-confirm-change`) swaps the subscription's price and toasts. Every paid change settles at once
  (`always_invoice`): an upgrade charges the prorated difference, a downgrade leaves it as Account balance; a Trial is
  never charged.
- A Complimentary Account (ADR 0006, `features/complimentary.md`) keeps the Plan name but hides the price, Change
  plan, Subscribe, payment methods, Manage on Stripe, the plan dialog and the Invoices tab; a strip says the Plan was
  given by The RX Desk with its end date. Checkout, Change plan, Subscribe and the portal answer with the toast
  "Your plan was given by The RX Desk; contact The RX Desk to subscribe."
- "Change plan" and "Subscribe" refuse an Account whose Owner country is blocked with the toast "Not available in
  the EU/UK yet": no swap and no Checkout Session. An Account whose completed checkout was billed to a blocked
  country (`accounts.blocked_country_at`) never reaches Billing at all: that column makes `hasAppAccess()` false, so
  `EnsureAccountReady` sends the Owner to `/welcome/plan`.
- After a billing-origin Stripe Checkout the buyer lands on `/billing/processing` (`features/welcome-checkout.md`):
  a spinner until the webhook records this Checkout Session's facts, then back to Billing. A Welcome-origin Checkout
  returns to the Schedule instead. A Thailand-billed buyer reads the Thai purchase document there and presses
  Continue; the same document is emailed once per Session.
- Payment methods (`@billing-payment-methods`, `@billing-card-<id>`), "Manage on Stripe" (`@billing-manage-stripe`,
  the Customer Portal).
- Invoices tab (`@billing-tab-invoices`, `@billing-invoices`, `@billing-invoice-download-<id>`, empty state
  `@billing-invoices-empty`); `/settings/account/billing?tab=invoices` deep-links to it.
- The System admin can refund and cancel a Thai purchase inside its 7-day window with
  `POST /internal/v1/accounts/{uuid}/refund-and-cancel`: the latest paid charge is refunded, the Subscription is
  cancelled at once (`ends_at` now, `cancellation_type = admin`) and `account.refund_and_cancel` is audited
  (`features/internal-api.md`).

## How to get to it (user POV)

In selling mode, with a subscribed Owner: open the account menu, choose "Plans and billing".

## Driving it with Playwright

- Journey: `$V/drive.sh billing`, right after `welcome-checkout` on the same fixture (it needs the Mini monthly
  subscription that journey makes; do not run `fixture.sh up` in between).
- Downgrade journey: `$V/drive.sh billing-downgrade`, on a paid Standard subscription past its Trial. End the
  fixture's Trial first (`$V/stripe.sh subscriptions update <sub> --trial-end now`), or run it after
  `stripe-clock.sh advance` past the Trial. The confirmation says the unused time becomes Account balance, and the
  current-plan card shows the credit after the change (`@billing-account-balance`).
- Checks: Mini, `$11.90`, Free trial (after `welcome-checkout` the first checkout is on the Catalog trial; the
  journey also accepts Active for a subscription past its trial, and for a repeat card that skipped the Trial); the
  `4242` card listed; Usage lists Storage and AI credits; the status strip says the Trial end or the renewal; the
  dialog's Yearly shows `$9.92` a month; choosing Standard opens the confirmation with `$17.90` and when it is
  charged; confirming makes it the current plan at `$17.90`; "Manage on Stripe" opens `billing.stripe.com` on the
  Standard subscription at `$17.90 per month`.
- Account menu: the Owner sees `@sidebar-menu-storage` and `@sidebar-menu-ai-credits` (the AI credits bar links to
  Billing `#usage`); a Member sees neither.
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
- The fixture Owner's country is `TH`, so the plan actions work. To see the refusal, set `users.country` to an EU/UK
  code on the fixture Owner and press "Change plan": the page stays and the toast shows the notice. Setting
  `accounts.blocked_country_at` instead removes access, so Billing redirects to `/welcome/plan`.
- Not driven yet: the Invoices tab, Subscribe on a generic trial, a yearly swap (the discount is kept only when its
  Offer covers yearly, and dropped otherwise), and the admin refund-and-cancel (no journey; prove it with
  `internal.sh POST /accounts/<id>/refund-and-cancel` and the `subscriptions` / `audit_logs` rows).
  `tests/Browser/BillingPageTest.php` covers the active upgrade and downgrade confirmation dialogs; the Trial
  no-charge and Offer kept/dropped confirmation branches are not browser-asserted. The downgrade's Account balance
  is `billing-downgrade`.
