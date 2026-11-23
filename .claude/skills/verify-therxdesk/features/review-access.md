# Reviewer accounts and Review access

A platform reviewer gets a test Account with a Complimentary Plan and no email
through `POST /internal/v1/accounts`, and a per-Account **Review access** switch
lets that Account connect and publish to networks that are **installed but
switched off**. A network the server has no credentials for stays unusable even
with Review access. Every set and flip is audited, never with the password.

## Customer-visible contract

- **Creating the Account.** `POST /internal/v1/accounts` takes `email`,
  `password`, `plan_id`, optional `complimentary_until` and optional
  `review_access`. It runs upstream's `CreateUser` (verified email, default
  Workspace, Owner), gives the Plan through `ComplimentaryPlans::give`, and sends
  no email. `422` for an email used by any User, an unknown Plan, or a password
  that fails the registration rules; an archived Plan is allowed. It answers
  `201` with the Account id, the owner email, the Plan, the Complimentary state
  and `review_access`. The audit row `account.create` holds the ids, email, Plan,
  end date and Review access, never the password. It works with new signups on
  or off, because it bypasses registration.
- **The switch.** `PATCH /internal/v1/accounts/{account}/review-access` with
  `{ "enabled": bool }` sets it and answers `{ id, review_access }`. Each call
  writes `account.review_access.enable` or `.disable` with `{ review_access }`
  before/after. Customer detail and the Customers list carry `review_access`.
- **Availability.** `Platform::isAvailableFor($account)` is installed AND
  (switched on OR the Account has Review access). A platform without an
  Availability switch (X) is unchanged, and a not-installed network stays
  blocked. `isConnectableFor` / `connectableOptionsFor` mirror the variant rules
  (LinkedIn profile/page, Instagram direct/via Facebook).
- **The app.** The shared `connectablePlatforms` / `unavailablePlatforms` props,
  the connect routes, `PlatformAvailability` (web save and the stored-post gate),
  `PublishingGate` and the LinkedIn variant checks all use the Account-aware
  form, so a reviewer sees the network's Connect card, gets no unavailable
  banner, can schedule, and a due post publishes instead of failing
  `unavailable`.

## How to drive it (host sequence)

Needs `selling.sh up` (internal API tokens and the fake LinkedIn credentials) and
the fixture. The fixture channel is LinkedIn. The selling overlay makes LinkedIn
installed, so Availability can switch it off and back on.

```sh
V=.claude/skills/verify-therxdesk/scripts

$V/launch.sh
$V/selling.sh up
$V/fixture.sh up
$V/complimentary.sh up

# Apply this branch's migrations: launch reuses an already-running stack.
( source "$V/env.sh"; app_run php artisan migrate --force )

# LinkedIn off (both variants), then the fixture Account gets Review access.
$V/internal.sh PATCH /availability/platform:linkedin --body '{"enabled":false}'
$V/internal.sh PATCH /availability/platform:linkedin-page --body '{"enabled":false}'
$V/review-access.sh on
$V/review-access.sh show        # true

# The Connect card and scheduling are allowed for the reviewer.
$V/drive.sh review-access

# Review access off: the same network is refused again.
$V/review-access.sh off
$V/drive.sh availability-off

# Restore every switch.
$V/internal.sh PATCH /availability/platform:linkedin --body '{"enabled":true}'
$V/internal.sh PATCH /availability/platform:linkedin-page --body '{"enabled":true}'
$V/review-access.sh show        # false
$V/complimentary.sh down
$V/fixture.sh down
```

The `review-access` journey schedules the post **7 days ahead** (`VERIFY_REVIEW_LEAD_DAYS`),
so the live dev scheduler never reaches the fixture's fake token. `fixture.sh
down` removes the post with its Workspace.

## Host-side proof

- `review-access.sh show` prints `true` after `on` and `false` after `off`; the
  same value is on `GET /internal/v1/accounts/{id}` as `review_access`.
- `db.sh "select review_access from accounts where id = '<accountId>'"` shows
  the switch, and `db.sh "select action, before, after from audit_logs where
  target_id = '<accountId>' order by created_at"` holds
  `account.review_access.enable` / `.disable` with `{ "review_access": ... }`.
- `drive.sh review-access` screenshots: `connect-card-visible` (the LinkedIn
  card is offered while the switch is off), `composer-allowed` (no unavailable
  banner), `composer-scheduled`, `after-schedule`, `scheduled-post` (the post is
  in the list). `drive.sh availability-off` then shows `connect-card-hidden`,
  `composer-unavailable`, `schedule-refused` with Review access off.
- `api.sh POST /posts` answers `201` for the reviewer while the switch is off,
  and `422` naming LinkedIn once Review access is off (see `availability.md`).

## Gotchas

- **Review access is per Account, not per User.** Every Owner and Member of that
  Account has it.
- **A missing credential wins.** `Platform::isInstalled()` is checked before
  Review access, so a network the server is not set up for stays unavailable.
- **The fixture is LinkedIn.** Switch off both `platform:linkedin` and
  `platform:linkedin-page`; the connect card stands for the whole network while
  either variant is on.
- **Restore the switches and the fixture.** Leave both LinkedIn switches enabled,
  run `review-access.sh off`, then `cleanup.sh` (or the sequence above).
