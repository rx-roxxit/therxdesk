# Complimentary Plans

The System admin gives an Account any Plan for free, outside Stripe, with or without an end date, and ends it with a
date that defaults to 30 days ahead (ADR 0006). There is no Checkout, no card, no invoice and no Trial.

## Sub-features

- **Give.** `POST /internal/v1/accounts/{uuid}/complimentary` with a `plan_id` (any Plan, archived included) and an
  optional `ends_at`. The Account's `plan_id`, `complimentary_since` and `complimentary_until` are written; app access,
  the Quota and the AI credits normally follow that Plan at once. A per-Account Quota or AI-credit override still wins,
  and an active generic Trial allowance wins for AI credits. Audited as `account.complimentary.give`.
- **End.** `POST /internal/v1/accounts/{uuid}/complimentary/end` with an optional `ends_at`, defaulting to 30 days
  ahead; an earlier date (now, or in the past) ends it at once. Audited as `account.complimentary.end`. Once the end
  passes the Account is Lapsed and the plan step shows `@welcome-plan-lapsed-deletion` (`lifecycle-deletion.md`). The
  30-day data-deletion clock starts on the next daily `rxdesk:lifecycle` sweep, not at the ending itself, so with a
  future `ends_at` it can trail the end by up to a day.
- **Plan step.** `/welcome/plan` shows the Plan, `Plan given by The RX Desk`, and the end date (`No end date` without
  one) in place of the cards: `@welcome-plan-complimentary`, `@welcome-plan-complimentary-plan`,
  `@welcome-plan-complimentary-until` or `@welcome-plan-complimentary-no-end`, and a Continue button
  (`@welcome-plan-complimentary-continue`) that opens the Schedule.
- **Billing.** `/settings/account/billing` keeps the Plan name and shows the same strip
  (`@billing-current-plan-complimentary`, `-until` / `-no-end`) with no price, Change plan, Subscribe, payment
  methods, Manage on Stripe, plan dialog or Invoices tab. The Usage section (`@billing-usage-storage`,
  `@billing-usage-ai`) still renders.
- **No self-service.** Checkout, Change plan, Subscribe and the Customer Portal answer with the toast
  `Your plan was given by The RX Desk; contact The RX Desk to subscribe.` The Account never gets a Trial afterwards,
  and the System admin ends the Plan before it can pay.
- **Customer detail.** `GET /internal/v1/accounts/{uuid}` carries `complimentary` (`in_force`, `since`, `until`) once
  one was ever given, `null` before.

## How a user reaches it

The System admin gives the Plan through the internal API (the admin app's action). The Owner signs in and lands on the
Schedule; from the account menu, "Plans and billing" shows the Complimentary state. To see the plan step, the Owner
opens `/welcome/plan` directly (the Schedule is where sign-in lands while the Plan is in force).

## How to drive it

Order matters: `selling.sh up` seeds the Plans, `complimentary.sh up` gives the fixture Account the Standard Plan, then
the journeys, then `complimentary.sh end` and the second journey.

```sh
$V/selling.sh up
$V/fixture.sh up                 # a fresh Owner with no persona (rerun after a previous drive)
$V/complimentary.sh up           # give Standard; a repeat call while it is in force writes no second give
$V/drive.sh complimentary        # plan step, Schedule, billing
$V/complimentary.sh end          # end it 1 minute ago: the Account is Lapsed at once
$V/drive.sh complimentary-ended  # /schedule -> /welcome/plan with the cards again
$V/complimentary.sh down         # end only if still in force, then remove the journey env
```

`drive.sh` passes `VERIFY_COMPLIMENTARY_PLAN` and `VERIFY_COMPLIMENTARY_ACCOUNT` from
`~/.cache/verify-therxdesk/state/complimentary.env`. The `complimentary` journey proves the no-end-date branch only.
After `end`, inspect the plan step for `@welcome-plan-lapsed-deletion` and its 30-day date; the ended journey does not
assert that selector.

## Host-side proof

```sh
$V/complimentary.sh show                 # the Account's complimentary block
$V/internal.sh GET /accounts/<uuid> --token admin   # complimentary: { in_force, since, until }
$V/db.sh "select action, actor, before, after from audit_logs where target_type = 'accounts' and target_id = '<accountId>' and action like 'account.complimentary.%' order by created_at"
```

While the Plan is in force the block reads `in_force: true`; after `complimentary.sh end` it reads `in_force: false`
with `until` one minute ago. The audit table holds one `account.complimentary.give` and one
`account.complimentary.end` row, actor `verify-admin@example.com` (the internal helper's email), each with its
`before` and `after`.

## Gotchas

- The fixture Owner keeps the persona, goals and software the `complimentary` journey saved. Run `fixture.sh up`
  before a repeat, then `complimentary.sh up` again; otherwise the journey lands on the plan step and its persona wait
  fails.
- `complimentary.sh up` needs selling mode (`selling.sh up`); with no fixture it fails.
- Ending a Complimentary Plan is immediate only because the helper sends a date one minute in the past. The admin
  app's default is 30 days ahead.
- Once the end date has passed the Account is Lapsed but the cards work again: Checkout is refused only while the
  Complimentary Plan is in force, so the plan step offers Subscribe (charged at once, no Trial because the Account
  had a Plan) and a 30-day deletion clock runs from the end.
