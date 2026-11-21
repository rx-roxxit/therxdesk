---
name: verify-therxdesk
description: Drive The RX Desk web app (the Laravel + Inertia/Vue dev stack on localhost:8000) in a real Chromium, the way a user does, and capture proof that a change works (screenshots, Playwright trace, console, database rows). Covers self-hosted mode and selling mode (Plans, Stripe sandbox Checkout, billing, webhooks). Use when you need to show a feature works in the running app, not only in tests, or to reproduce a bug in the UI.
---

# Verify The RX Desk

Proves behavior in the running dev app: sign in as a throwaway Owner, click through a feature in Chromium
(Playwright 1.61.1 in its official image), and keep the screenshots, trace and database rows as evidence. Tests
(`tests/Browser`, Pest + Playwright) prove code paths in isolation; this proves the assembled app a user opens.

Every helper lives in `scripts/` and runs from anywhere in the checkout. Set `V=.claude/skills/verify-therxdesk/scripts`
from the repo root and call them as `$V/<name>`.

## What this drives, and what it must not touch

- **The dev stack of the main checkout, only.** `docker compose up` in the main checkout serves that checkout's files
  (bind mount) with Vite hot reload. It is the owner's own dev instance, so there is no second, isolated copy.
  Verification works beside the owner, never on their data:
    - All driving happens as the fixture Owner `verify-agent@example.com` (password `password`), in a fresh
      browser context. Never sign in as, or change, any other user.
    - One run at a time. The fixture is one fixed email, and `fixture.sh up` replaces it, so two runs at once
      corrupt each other.
    - Never `docker compose down -v` (it wipes the owner's dev database), never `exec` into `app` as root, never
      kill a process by name.
- **From a git worktree** the scripts still drive the main checkout's stack, which serves the main checkout's
  branch, not your worktree's. `doctor.sh` fails in that case. Stop and report; do not switch the main checkout's
  branch yourself.
- **Never production.** No script reads or writes anything outside this machine's dev stack.
- **Two modes, `.env` untouched.** The dev `.env` says `SELF_HOSTED=true`: billing, Plans and checkout are off.
  `selling.sh up` switches the running stack to selling mode without editing `.env` (see "Selling mode"), and
  `selling.sh down` switches it back. Never edit `SELF_HOSTED` or any other `.env` value; ask the owner first.
- **Never print a secret.** `STRIPE_KEY`, `STRIPE_SECRET` and the webhook signing secret stay out of every output:
  never `cat .env`, `docker compose config`, `docker inspect` an env list, or read the listener's raw log
  (`compose logs stripe` prints the signing secret). Use `stripe.sh`, `selling.sh status` and `selling.sh logs`.

## Launch

```sh
$V/launch.sh
```

It reuses the stack if `app` is already running (and records that it did not start it), else runs `docker compose
up -d`. The first boot from empty volumes runs `composer install`, `npm ci` and migrations, so it takes minutes. It
then waits until the stack is usable, not merely up: the `app` container is `healthy` (the entrypoint finished
migrations, Passport keys and Wayfinder), `GET /up` answers, the Vite dev server serves `/@vite/client`, and
`/login` renders an Inertia page. Last, it creates the fixture (`fixture.sh up`) and prints it as JSON:
`email`, `password`, `userId`, `accountId`, `workspaceId`, `channelId` (a made-up LinkedIn channel), `channelPlatform`.

The fixture is one Owner with one Workspace ("Verify Desk") and one LinkedIn channel created without model events,
so nothing calls LinkedIn with its fake token. Run `$V/fixture.sh up` again any time to reset it to that state.

## Selling mode

```sh
$V/selling.sh up        # selling mode: Plans, Stripe sandbox, webhooks
$V/selling.sh status    # marker, the container's SELF_HOSTED, what php-fpm serves, the listener
$V/selling.sh logs      # the Stripe listener's log, signing secret redacted
$V/selling.sh down      # back to .env's mode (self-hosted)
```

Run `launch.sh` first; `up` needs the stack running. It does four things, each safe to repeat:

1. **Plan catalog.** Runs `php artisan migrate --force` and then `Database\Seeders\PlanSeeder` in a one-off
   container (both idempotent, no env): the seeder writes the four RX Desk Plans and their `plan_prices` rows into
   the app's own database (Mini $11.90 / $119, Standard $17.90 / $179, Plus $29.90 / $299, Max $49.90 / $499;
   yearly is 10× monthly) and archives any other Plan. The rows carry no Stripe ids.
2. **Sandbox prices.** `scripts/plans.php catalog` prints the active Plans' active price rows as JSON. Through
   `stripe.sh` (the Stripe CLI image `stripe/stripe-cli:v1.55.14`, nothing installed on the host), it finds or
   creates one Product per Plan and one Price per interval, keyed by lookup keys
   `rxdesk-verify-<slug>-monthly|yearly`, with the amount from the catalog (never written in bash). A second run
   creates nothing; a sandbox Price whose amount no longer matches the catalog stops `up`, because a Stripe Price
   is immutable. `scripts/plans.php attach` then writes each id into that Plan and interval's active `plan_prices`
   row: the same id is a no-op, and a different id adds an active row and archives the old one (no price row is
   ever deleted). `stripe.sh` refuses a key that is not a test key.
3. **Webhook secret.** `stripe listen --print-secret` (stable for the sandbox key) goes into
   `~/.cache/verify-therxdesk/state/stripe-webhook.env`, mode 600, never shown.
4. **Recreate.** It writes the marker `~/.cache/verify-therxdesk/state/selling` and recreates `app` with
   `scripts/compose.selling.yaml` on top of the project's compose files: `SELF_HOSTED=false` and
   `STRIPE_WEBHOOK_SECRET` as container env (Laravel keeps container env over `.env`, and php-fpm passes it
   through, `clear_env = no`), plus a `stripe` service running `stripe listen --all-snapshot --forward-to
   http://app/stripe/webhook`. It waits until the app is usable, php-fpm serves `selfHosted=false`, and the
   listener is ready.

While the marker exists, every script (`compose` in `env.sh`) includes the overlay, so `compose stop`, `run` and
`up` see the same services. The owner's own `docker compose` commands do not: they warn about the orphan
`therxdesk-stripe-1` container (never pass `--remove-orphans`; `selling.sh down` removes it), and a plain
`docker compose up -d` recreates `app` in self-hosted mode, which `doctor.sh` reports as a mismatch.

Switching recreates the owner's running `app` container (about a minute; the entrypoint runs again), so the
owner's open dev browser follows the mode too. `cleanup.sh` runs `selling.sh down`.

The catalog is **temporary**: `PlanSeeder` writes the four Plans and their prices in code. Ticket #54 has the app
create every Stripe Product and Price itself from its Catalog; when it lands, `selling.sh` uses that sync instead
of creating sandbox prices, and the `rxdesk-verify-*` lookup keys go. Do that through
`/pstack:maintain-verification-skill`.

## Doctor

```sh
$V/doctor.sh
```

Read-only. Run it first, and again whenever anything looks off. It checks: `app` healthy, the stack serves this
checkout (and prints the served branch, commit and count of uncommitted paths), `/up`, the Vite dev server,
`public/hot` (without it the app serves the last `npm run build`, not your current frontend), `/login` renders, the
host/container clock skew (a skewed WSL clock breaks Passport and cache locks), the mode php-fpm serves (the login
page's `selfHosted` prop) against the `selling.sh` marker, and in selling mode the Stripe listener and the secret
file's mode, the fixture exists, the Playwright image and the `node_modules` volume. Exit 1 means do not drive yet: fix what it names.

## Drive

```sh
$V/drive.sh <journey>              # evidence in ~/.cache/verify-therxdesk/runs/<UTC time>-<journey>/
$V/drive.sh <journey> <folder>     # evidence in <folder>
```

A journey is a key of `JOURNEYS` in `scripts/drive.ts`. `drive.sh` runs it with `node` in
`mcr.microsoft.com/playwright:v1.61.1-noble` on the host network, against `http://localhost:8000`, with the repo
mounted read-only, the dev `node_modules` volume for `playwright`, and the fixture in `VERIFY_FIXTURE`. It prints
`ok`/`FAIL` per check and exits non-zero when the journey fails.

Journeys today: `sign-in`, `compose-draft` (self-hosted mode); `register`, `selling-mode`, `welcome-checkout`,
`billing` (selling mode, listed in `SELLING_JOURNEYS`). Every journey first checks the mode php-fpm serves and stops
with "run selling.sh up|down" when it is the other one. `features/README.md` maps every feature, with or without a
journey.

Writing a journey (add it to `JOURNEYS`, then a row and a file in `features/`):

- Drive the real user path: the login form (`signIn(run)`), visible buttons and links. Never call an internal
  endpoint, set a model through tinker mid-journey, or use a test-only route to reach the state you want to prove.
- Select by `data-testid` with `tid('@name')` (same `@name` shorthand as the Pest browser tests). When an element has
  none, use `page.getByRole(role, { name })`: the fixture Owner's locale is English. Never CSS classes, coordinates
  or tab order.
- `quietFirstVisits(run)` marks the first-visit tours as seen (browser storage only), so a tour popover does not
  cover the page. Reload after it.
- `run.step(name)` screenshots after each action that changes what the user sees. Wait for the end state before
  the last step: lists paint skeletons first.
- `run.check(name, passed, observed)` records a check with the value seen. `run.note(key, value)` records what the
  host-side checks need (a caption, an id).
- Radix submenus (the sidebar language menu) need a `pointermove` before the click; see
  `tests/Browser/SidebarLanguageSwitchTest.php`.
- `drive.ts` is TypeScript run by Node's type stripping. The repo's ESLint forbids relative imports, which is why
  the harness and journeys share one file. Run `npx eslint .claude/skills/verify-therxdesk` (through the app image)
  after editing it.

Side effects are checked from the host, after the journey, with read-only SQL:

```sh
$V/db.sh "select id, status, content from posts where workspace_id = '<workspaceId>'"
```

`db.sh` opens a read-only transaction on the dev database (`trypost`), so a write fails instead of landing.

## Evidence

Each run folder holds: `NN-<step>.png` (full-page screenshots in order), `trace.zip` (every action with DOM
snapshots and network: `npx playwright show-trace trace.zip`), `console.log` (console messages and page errors),
`result.json` (journey, pass/fail, crash stack, the fixture ids, steps, each check with its observed value, notes).

A proof is complete when it shows:

- the action and the resulting state, in screenshots taken after the state settled, not just a final screen;
- the side effect, from `db.sh` (the row written, the status changed), alongside what is visible; paste the query
  and its rows into your report;
- no JavaScript errors (every journey checks this);
- the served code: the branch and commit from `doctor.sh`.

Selling-mode journeys pay in the Stripe sandbox for real (test card `4242 4242 4242 4242`) and the webhooks arrive
through the listener: that is the proof, not a fake. Check the deliveries with `selling.sh logs` (each event
`<-- [200]`) and the result with `db.sh` and `stripe.sh`. Stripe's own pages (Checkout, Customer Portal) have no test
ids of ours: their selectors live in `payOnStripeCheckout()` in `drive.ts`.

Mocks only where a production boundary already isolates the outside system: the fixture's channel has a fake
token, so a journey must never publish or schedule a post into the past (the dev scheduler and Horizon are live
and would call the network). Save drafts, or schedule far ahead and remove the fixture afterwards.

Look at the screenshots before you claim anything. A passing check on a skeleton or a covered page proves nothing.

## Cleanup

```sh
$V/cleanup.sh
```

Removes the fixture Owner and everything it owns (`fixture.sh down`: its Workspace through `PurgeWorkspace`, its
sandbox Customer, which cancels its subscriptions, then the user and the Account), then runs `selling.sh down`. Stops the stack only if `launch.sh` started it, then hands the files the root-run
entrypoint wrote back to UID 1001. Leaves an already-running stack up. Never deletes evidence: it stays in
`~/.cache/verify-therxdesk/runs/` (override with `VERIFY_EVIDENCE_ROOT`). Run cleanup after every failed attempt too.

## Helpers

| Script | Does | Writes? |
| --- | --- | --- |
| `launch.sh` | start or reuse the stack, wait until usable, create the fixture | stack, fixture |
| `doctor.sh` | is this instance worth driving | no |
| `fixture.sh up\|show\|down\|guests` | create, print (JSON), remove the fixture Owner; `guests` removes register-journey Owners | `up`, `down`, `guests` |
| `drive.sh <journey> [folder]` | run one journey in Playwright, write evidence | evidence only |
| `db.sh "<select>"` | read-only SQL on the dev database | no |
| `selling.sh up\|down\|status\|logs` | switch the stack to selling mode and back, show it | stack, sandbox catalog, `plan_prices` |
| `stripe.sh <stripe cli args>` | one Stripe CLI command on the sandbox (`get /v1/customers -d email=...`) | what you ask |
| `cleanup.sh` | remove the fixture, leave selling mode, stop the stack if we started it | fixture, stack |

`env.sh` holds the shared settings (`VERIFY_BASE_URL`, `VERIFY_VITE_URL`, `VERIFY_EVIDENCE_ROOT` override them),
`compose` (with the selling overlay while the marker exists), `wait_until_usable` and `served_mode`.
`compose.selling.yaml` is the selling overlay.
`fixture.php` and `plans.php` run inside the app image as `app` (UID 1001) through `docker compose run`, never as root.
