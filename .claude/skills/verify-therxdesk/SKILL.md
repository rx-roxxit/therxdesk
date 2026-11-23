---
name: verify-therxdesk
description: Drive The RX Desk web app (the Laravel + Inertia/Vue dev stack on localhost:8000) in a real Chromium, the way a user does, and capture proof that a change works (screenshots, Playwright trace, console, database rows). Covers self-hosted mode and selling mode (Plans, Stripe sandbox Checkout, billing, webhooks). Use when you need to show a feature works in the running app, not only in tests, or to reproduce a bug in the UI.
---

# Verify The RX Desk

Proves behavior in the running dev app: sign in as a throwaway Owner, click through a feature in Chromium
(Playwright 1.65.1 in its official image), and keep the screenshots, trace and database rows as evidence. Tests
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
`email`, `password`, `userId`, `accountId`, `stripeId`, `workspaceId`, `channelId` (a made-up LinkedIn channel),
`channelPlatform`, `memberEmail` and `memberId` (a Member of the Workspace on the same Account).

The fixture is one Owner with one Workspace ("Verify Desk"), one LinkedIn channel created without model events,
so nothing calls LinkedIn with its fake token, and one Member (password `password`). Run `$V/fixture.sh up` again
any time to reset it to that state.

## Selling mode

```sh
$V/selling.sh up        # selling mode: Plans, Stripe sandbox, webhooks
$V/selling.sh status    # marker, the container's SELF_HOSTED, what php-fpm serves, the listener
$V/selling.sh logs      # the Stripe listener's log, signing secret redacted
$V/selling.sh down      # back to .env's mode (self-hosted)
```

Run `launch.sh` first; `up` needs the stack running. It does five things, each safe to repeat:

1. **Plan catalog.** Runs `php artisan migrate --force` and then `Database\Seeders\PlanSeeder` in a one-off
   container (both idempotent, no env): the seeder writes the four RX Desk Plans and their `plan_prices` rows into
   the app's own database (Mini $11.90 / $119, Standard $17.90 / $179, Plus $29.90 / $299, Max $49.90 / $499;
   yearly is 10× monthly) and archives any other Plan. The rows carry no Stripe ids; step 2 gives them theirs.
2. **Catalog sync.** Runs `php artisan rxdesk:catalog-sync` in the app image. The app creates every Stripe object
   itself: one Product per active Plan, a monthly Price and a yearly Price at ten times monthly, with the amounts
   read from `plan_prices` (never written in bash). It writes each Price id into its Plan and interval's active
   `plan_prices` row: the same id and amount is a no-op, and a different one adds an active row and archives the
   old one (no price row is ever deleted). A Stripe key change makes the stored ids unreachable, so the same
   command creates new Products and Prices and writes new rows, leaving the old rows to keep resolving.
   `stripe.sh` refuses a key that is not a test key.
3. **Legacy sandbox objects.** Sandbox Products and Prices that earlier runs created with a `rxdesk-verify-*`
   lookup key or `metadata.rxdesk_verify=true` are archived (`active=false`), never deleted. The step is
   idempotent, so finding none is normal.
4. **Webhook secret.** `stripe listen --print-secret` (stable for the sandbox key) goes into
   `~/.cache/verify-therxdesk/state/stripe-webhook.env`, mode 600, never shown.
5. **Recreate.** It writes the marker `~/.cache/verify-therxdesk/state/selling` and recreates `app` with
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

The catalog is the **app's own data**. `plan_prices` is the source of truth and the app creates every Stripe
object from it; `selling.sh up` seeds the four Plans only because a fresh dev database has none. In the running
app, the same Catalog is edited through `/internal/v1` (`POST /plans`, `PATCH /plans/{id}`,
`POST /plans/{id}/archive`, `PATCH /catalog-settings`), and each write is audited. `PlanSeeder` stays the dev
seed, not a second catalog: do not hand-create sandbox Products or Prices beside it. The `GET /plans` response
shows the Stripe ids the app stored.

## Trial and payment

The four paths of the Trial and access rules (a repeat card, a failed first charge, a paid-before past due, and a
lapse) need a Stripe **test clock**, so they finish in a minute instead of days:

```sh
$V/stripe-clock.sh create              # a clock frozen at now
$V/stripe-clock.sh customer            # a sandbox Customer on it
$V/stripe-clock.sh attach              # fixture.sh customer <cus_...>
$V/stripe-clock.sh advance <epoch>     # move it, wait until Stripe is ready
$V/stripe-clock.sh status              # clock, status, frozen time, Customer
$V/stripe-clock.sh delete              # remove it (cleanup.sh does this)
```

`fixture.sh card <fingerprint>` claims a card for the fixture Account (a repeat card), and
`stripe-clock.sh fingerprint [pm_id]` prints a payment method's fingerprint. `fixture.sh up` deletes the Account's
`trial_cards` rows, so `4242` starts a fresh Trial on every run. The commands, the journeys and the expected rows are
in `features/trial-and-payment.md`.

## Suspension and the publishing gate

```sh
$V/suspension.sh up            # suspend the fixture Account through /internal/v1 (reason in the audit log)
$V/suspension.sh show          # the Account's access_state (suspended, lapsed, has_app_access)
$V/suspension.sh lift          # lift it, remove the journey env
$V/api.sh GET /posts           # the public API as the fixture Owner, a fresh token never printed
```

`up` needs `selling.sh up` (the internal tokens), resolves the fixture Account through `fixture.sh show`, and writes
`VERIFY_SUSPENSION_ACCOUNT` to `state/suspension.env`; `down` lifts what the env recorded. While suspended the app
answers every page with the Suspended screen (`/welcome/suspended`) for the Owner and every Member, and every `/api`
route answers `402` with `Account suspended, contact support.` The publishing gate then fails a scheduled post with
`posts.publish_block_reason = suspended`; once lifted, the Owner's **Try again** re-queues it. `features/suspension.md`
holds the full sequence (the gate journeys need `complimentary.sh up` for access, and a suspend inside the 5-minute
scheduling lead).

## Lifecycle emails and the deletion clock

```sh
$V/fixture.sh lifecycle 168   # Lapsed fixture, deletion clock 7 days out
$V/lifecycle.sh sweep         # send the due emails and flush the queue
$V/lifecycle.sh emails        # the Owner and Member mail in Mailpit
$V/lifecycle.sh export        # rxdesk:export --dry-run
$V/lifecycle.sh delete-dry    # rxdesk:delete --dry-run
$V/lifecycle.sh delete        # rxdesk:delete for the fixture only
$V/lifecycle.sh show          # access_state, including deletion_scheduled_at
```

`fixture.sh lifecycle <hours>` puts the fixture Owner on the Lapsed path (completed onboarding, a cancelled
subscription, `first_paid_at`) with `accounts.deletion_scheduled_at` that many hours out (`-1` is already due).
`lifecycle.sh sweep` runs `rxdesk:lifecycle --account=<fixture>` and then `queue:work --stop-when-empty`, because the
dev stack has no worker and the emails are queued. Both CLI commands take `--account` and `--dry-run`, and are off
while self-hosted. `drive.sh lapsed-deletion` proves the plan step shows the date. `features/lifecycle-deletion.md`
has the full sequence and the database rows to expect.

## Media on R2

```sh
$V/media.sh up      # recreate app with FILESYSTEM_DISK=r2 (marker + compose.r2.yaml)
$V/media.sh down    # remove the marker and recreate app from .env's disk
$V/media.sh seed    # the proof channel, avatar and follower readings
$V/media.sh clear   # remove the proof channel, readings and avatar
```

`up` and `down` never edit `.env`: the R2 `FILESYSTEM_DISK=r2` is container env from the skill's
overlay, and Laravel keeps the container value over `.env`. The r2 marker stacks on the selling
overlay, so whichever is switched last recreates the app with both. `seed` refuses to run unless the
app serves the r2 disk. The proof needs a subscribed Owner (run `welcome-checkout` once) and ends with
`media.sh clear`, `media.sh down`, `selling.sh down`; `cleanup.sh` does the last two.

## Internal API

Tokens are generated by `selling.sh up` into `internal-api.env` (mode 600) and never printed. Use `internal.sh` to call `/internal/v1` endpoints; see `features/internal-api.md`.

## Doctor

```sh
$V/doctor.sh
```

Read-only. Run it first, and again whenever anything looks off. It checks: `app` healthy, the stack serves this
checkout (and prints the served branch, commit and count of uncommitted paths), `/up`, the Vite dev server,
`public/hot` (without it the app serves the last `npm run build`, not your current frontend), `/login` renders, the
host/container clock skew (a skewed WSL clock breaks Passport and cache locks), the mode php-fpm serves (the login
page's `selfHosted` prop) against the `selling.sh` marker, the media disk against the `media.sh` marker, and in
selling mode the Stripe listener and the secret
file's mode, the fixture exists, the Playwright image and the `node_modules` volume. Exit 1 means do not drive yet:
fix what it names.

## Drive

```sh
$V/drive.sh <journey>              # evidence in ~/.cache/verify-therxdesk/runs/<UTC time>-<journey>/
$V/drive.sh <journey> <folder>     # evidence in <folder>
```

A journey is a key of `JOURNEYS` in `scripts/drive.ts`. `drive.sh` runs it with `node` in
`mcr.microsoft.com/playwright:v1.65.1-noble` on the host network, against `http://localhost:8000`, with the repo
mounted read-only, the dev `node_modules` volume for `playwright`, and the fixture in `VERIFY_FIXTURE`. It prints
`ok`/`FAIL` per check and exits non-zero when the journey fails. `VERIFY_TEST_CARD` overrides the `4242` card
`payOnStripeCheckout()` fills (the failed-first-charge path uses it).

Journeys today: `sign-in`, `compose-draft`, `storage-bar`, `language-switch` (self-hosted mode); `register`,
`selling-mode`, `welcome-plan`, `welcome-checkout`, `billing`, `complimentary`, `complimentary-ended`,
`payment-retry`, `past-due`, `lapsed`, `media-on-r2`, `suspension`, `gate-schedule`, `gate-retry`,
`availability-off`, `availability-retry`, `review-access`, `services-off`
(selling mode, listed in `SELLING_JOURNEYS`; `complimentary` needs `complimentary.sh up` and
`complimentary-ended` needs `complimentary.sh end` after it, `payment-retry`, `past-due` and `lapsed` need a Stripe
test clock, `media-on-r2` also needs `media.sh up`, `suspension` needs `suspension.sh up`, `gate-schedule` needs
`complimentary.sh up` and a `suspension.sh up` right after, `gate-retry` needs `suspension.sh lift` and
`VERIFY_BLOCKED_POST` from `gate-schedule`'s `notes.blockedPostId`; the availability journeys switch both
`platform:linkedin` variants off through `internal.sh` first, `review-access` needs `review-access.sh on` after that,
and `availability-retry` needs them back on and the same
`VERIFY_BLOCKED_POST` after the scheduler blocked it). Every journey first checks the mode php-fpm
serves and stops with "run selling.sh up|down" when it is the other one. `features/README.md` maps every feature,
with or without a journey.

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

## Browser suite

```sh
$V/browser-suite.sh                                    # the whole suite, three processes
$V/browser-suite.sh tests/Browser/BillingPageTest.php  # one file
```

`tests/Browser` never runs in the Alpine dev image: the bundled Chromium needs glibc, and the Pest browser plugin
needs a Playwright at least as new as the version it pins. `browser-suite.sh` builds a throwaway image from the
official Playwright image at the version installed in `node_modules` plus PHP 8.5 (it needs `rsync` and a built
`public/build`), copies the checkout to `~/.cache/verify-therxdesk/state/browser-suite/repo` without `public/hot`,
and runs the suite against the dev stack's `pgsql` and `redis`. Screenshots and traces stay in that copy.
`browser-suite.sh` is the only place this suite runs today: the fork's GitHub Actions have never run, so the
workflow's `browser` job does not run on its own.

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

Removes the fixture Owner, the Member and everything they own (`fixture.sh down`: the Workspace through
`PurgeWorkspace`, its sandbox Customer, which cancels its subscriptions, then the users and the Account), after
lifting a Suspension (`suspension.sh down`). Then it deletes the Stripe test clock
(`stripe-clock.sh delete`), and runs `selling.sh down` and `media.sh down`. Stops the stack only if `launch.sh` started
it, then hands the files the root-run entrypoint wrote back to UID 1001. Leaves an already-running stack up. Never
deletes evidence: it stays in `~/.cache/verify-therxdesk/runs/` (override with `VERIFY_EVIDENCE_ROOT`). Run cleanup
after every failed attempt too.

## Helpers

| Script | Does | Writes? |
| --- | --- | --- |
| `launch.sh` | start or reuse the stack, wait until usable, create the fixture | stack, fixture |
| `doctor.sh` | is this instance worth driving | no |
| `fixture.sh up\|show\|down\|guests` | create, print (JSON), remove the fixture Owner, its Workspace and the Member (`memberEmail`, `memberId`); `customer` / `customer-clear` point its Account at a sandbox Customer; `card` claims a fingerprint; `token` issues a personal API token for `api.sh`, never printed; `guests` removes register-journey Owners | `up`, `down`, `guests`, `customer`, `customer-clear`, `card`, `token` |
| `drive.sh <journey> [folder]` | run one journey in Playwright, write evidence | evidence only |
| `browser-suite.sh [pest args]` | run `tests/Browser` in the Playwright + PHP image | a scratch copy under `state/` |
| `db.sh "<select>"` | read-only SQL on the dev database | no |
| `selling.sh up\|down\|status\|logs\|heartbeat` | switch the stack to selling mode and back, show it | stack, Stripe sandbox objects, `plan_prices` |
| `media.sh up\|down\|seed\|clear` | switch the media disk to r2 and back; seed or remove the media proof | stack, `medias` rows, the bucket |
| `mail.sh list\|latest\|wait\|show\|clear` | read the dev Mailpit inbox | `clear` empties it |
| `stripe.sh <stripe cli args>` | one Stripe CLI command on the sandbox (`get /v1/customers -d email=...`) | what you ask |
| `complimentary.sh up\|end\|show\|down` | give the fixture a Complimentary Plan through `/internal/v1`, end it, show it | `up`, `end`, `down` |
| `review-access.sh on\|off\|show` | grant or revoke the fixture Account's Review access through `/internal/v1`, show it | `on`, `off` |
| `suspension.sh up\|lift\|show\|down` | suspend the fixture Account through `/internal/v1`, lift it, print its `access_state` | `up`, `lift`, `down` |
| `api.sh <METHOD> <path>` | call the public `/api` as the fixture Owner with a fresh personal token, never printed | an API token row |
| `stripe-clock.sh create\|customer\|attach\|advance\|status\|fingerprint\|delete` | a Stripe test clock, its Customer and the fixture Account | Stripe sandbox, fixture, state |
| `cleanup.sh` | remove the fixture, leave selling mode, stop the stack if we started it | fixture, stack |
| `internal.sh <METHOD> <path> ...` | call `/internal/v1` with a generated token, never printed | no |

`env.sh` holds the shared settings (`VERIFY_BASE_URL`, `VERIFY_VITE_URL`, `VERIFY_EVIDENCE_ROOT` override them),
`compose` (with the selling overlay while the marker exists), `wait_until_usable` and `served_mode`.
`compose.selling.yaml` is the selling overlay.
`fixture.php` runs inside the app image as `app` (UID 1001) through `docker compose run`, never as root; the
Catalog sync runs the same way through `app_run php artisan rxdesk:catalog-sync`.
