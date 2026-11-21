# Billing

The Owner sees the Account's Plan, price and status, changes Plan or interval in a dialog, sees the cards and
invoices, and manages the subscription on Stripe's Customer Portal.

## Sub-features

- Entry: account menu (`@sidebar-workspace-menu`) → `@sidebar-menu-billing`, or Settings → `@settings-nav-billing`.
  Owner only (a member gets 403); selling mode only.
- Plan tab: current plan (`@billing-current-plan-name`, `-price`, `-badge`, `-status` for a trial, cancel or past
  due, `-discount`), "Change plan" (`@billing-change-plan`), "Subscribe" (`@billing-subscribe`, a generic trial).
- Plan dialog (`@billing-plan-dialog`, `PlanPicker`): Monthly / Yearly (`@plan-interval-<interval>`,
  `@plan-save-two-months`), a card per Plan (`@plan-card-<slug>`, `@plan-price-<slug>`, `@plan-select-<slug>`).
  Choosing a Plan swaps the subscription's price at once (proration charged to the card); without a subscription
  it opens Checkout.
- Payment methods (`@billing-payment-methods`, `@billing-card-<id>`), "Manage on Stripe" (`@billing-manage-stripe`,
  the Customer Portal).
- Invoices tab (`@billing-tab-invoices`, `@billing-invoices`, `@billing-invoice-download-<id>`).

## How to get to it (user POV)

In selling mode, with a subscribed Owner: open the account menu, choose "Billing".

## Driving it with Playwright

- Journey: `$V/drive.sh billing`, right after `welcome-checkout` on the same fixture (it needs the Socials monthly
  subscription that journey makes; do not run `fixture.sh up` in between).
- Checks: Socials, `$19`, Active; the `4242` card listed; the dialog's Yearly shows `$15.83`; choosing Workspaces
  makes it the current plan at `$99`; "Manage on Stripe" opens `billing.stripe.com`.
- Side effect: the same `db.sh` query as `welcome-checkout` shows `workspaces | active | <the
  rxdesk-verify-workspaces-monthly price>`.

## Gotchas

- The dialog fades in: the journey waits for `document.getAnimations()` to finish before its screenshot.
- The upgrade changes the fixture's subscription for good; run `welcome-checkout` from a fresh fixture to repeat.
- The Customer Portal works with the sandbox's default portal settings; cancelling there is not driven.
- Not driven yet: the Invoices tab, Subscribe on a generic trial, a downgrade, a yearly swap (which drops the
  discount), a past-due or cancelled subscription.
