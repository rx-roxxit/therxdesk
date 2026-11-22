# Selling mode

The app sells Plans: an Owner without app access (no subscription and no no-card trial) is held at Welcome until
they check out; a member of such an Account waits on "Waiting for the account owner". A no-card trial already has
app access, so its Owner goes straight to the Schedule. Self-hosted mode skips all of it.

## Sub-features

- `config('trypost.self_hosted')` false: `EnsureAccountReady` sends an Owner without app access to
  `/welcome/plan` (which first walks the unfinished Welcome steps), a member to `/welcome/subscription-required`.
- Shared Inertia props: `selfHosted` (every page, the login page included), `auth.hasActiveSubscription`,
  `auth.plan`, `plans` (the active Plans, from `plan_prices`: Mini $11.90 / $119, Standard $17.90 / $179, Plus
  $29.90 / $299, Max $49.90 / $499; empty in self-hosted mode), `usage` and `features`.
- Plans, prices and Stripe ids are app data: `plan_prices` holds the active monthly and yearly rows per Plan (a
  price change archives the old row instead of deleting it, so a Plan may hold several inactive rows), and
  `selling.sh` seeds the catalog and fills the Stripe ids (see `SKILL.md`, "Selling mode").
- Billing and Processing answer only here (they redirect to the Schedule when self-hosted); the Welcome plan step
  does too.

## How to get to it (user POV)

`$V/selling.sh up`, then sign in as the fresh fixture Owner: instead of the Schedule, Welcome asks "What best
describes you?".

## Driving it with Playwright

- Journey: `$V/drive.sh selling-mode` (after `fixture.sh up`, so the Owner has no subscription).
- Checks: the login page's `selfHosted` prop is `false` (what php-fpm serves, not only the container env), sign-in
  lands on a `/welcome` path, `auth.hasActiveSubscription` is `false`.
- From the host: `$V/selling.sh status` (marker, the container's `SELF_HOSTED`, served mode, listener) and
  `$V/doctor.sh` (fails when the marker and the served mode disagree).

## Gotchas

- In selling mode the self-hosted journeys (`sign-in`, `compose-draft`) fail at once: the fixture Owner has no
  subscription and lands on Welcome. `drive.sh` stops with "run selling.sh down".
- A plain `docker compose up -d` by the owner recreates `app` from `.env` (self-hosted) while the marker still says
  selling: `doctor.sh` fails; run `selling.sh up` or `down`.
