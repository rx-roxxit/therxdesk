---
name: verify-therxdesk
description: Drive The RX Desk web app (the Laravel + Inertia/Vue dev stack on localhost:8000) in a real Chromium, the way a user does, and capture proof that a change works (screenshots, Playwright trace, console, database rows). Use when you need to show a feature works in the running app, not only in tests, or to reproduce a bug in the UI.
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
- **Self-hosted mode.** The dev `.env` runs with `SELF_HOSTED=true`, so billing, Plans and checkout are off. A
  journey for them fails; do not flip the env to make it pass. Report it instead.

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

## Doctor

```sh
$V/doctor.sh
```

Read-only. Run it first, and again whenever anything looks off. It checks: `app` healthy, the stack serves this
checkout (and prints the served branch, commit and count of uncommitted paths), `/up`, the Vite dev server,
`public/hot` (without it the app serves the last `npm run build`, not your current frontend), `/login` renders, the
host/container clock skew (a skewed WSL clock breaks Passport and cache locks), the fixture exists, the Playwright
image and the `node_modules` volume. Exit 1 means do not drive yet: fix what it names.

## Drive

```sh
$V/drive.sh <journey>              # evidence in ~/.cache/verify-therxdesk/runs/<UTC time>-<journey>/
$V/drive.sh <journey> <folder>     # evidence in <folder>
```

A journey is a key of `JOURNEYS` in `scripts/drive.ts`. `drive.sh` runs it with `node` in
`mcr.microsoft.com/playwright:v1.61.1-noble` on the host network, against `http://localhost:8000`, with the repo
mounted read-only, the dev `node_modules` volume for `playwright`, and the fixture in `VERIFY_FIXTURE`. It prints
`ok`/`FAIL` per check and exits non-zero when the journey fails.

Journeys today: `sign-in`, `compose-draft`. `features/README.md` maps every feature, with or without a journey.

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

Mocks only where a production boundary already isolates the outside system: the fixture's channel has a fake
token, so a journey must never publish or schedule a post into the past (the dev scheduler and Horizon are live
and would call the network). Save drafts, or schedule far ahead and remove the fixture afterwards.

Look at the screenshots before you claim anything. A passing check on a skeleton or a covered page proves nothing.

## Cleanup

```sh
$V/cleanup.sh
```

Removes the fixture Owner and everything it owns (`fixture.sh down`: its Workspace through `PurgeWorkspace`, then
the user and the Account). Stops the stack only if `launch.sh` started it, then hands the files the root-run
entrypoint wrote back to UID 1001. Leaves an already-running stack up. Never deletes evidence: it stays in
`~/.cache/verify-therxdesk/runs/` (override with `VERIFY_EVIDENCE_ROOT`). Run cleanup after every failed attempt too.

## Helpers

| Script | Does | Writes? |
| --- | --- | --- |
| `launch.sh` | start or reuse the stack, wait until usable, create the fixture | stack, fixture |
| `doctor.sh` | is this instance worth driving | no |
| `fixture.sh up\|show\|down` | create, print (JSON), remove the fixture Owner | `up`, `down` |
| `drive.sh <journey> [folder]` | run one journey in Playwright, write evidence | evidence only |
| `db.sh "<select>"` | read-only SQL on the dev database | no |
| `cleanup.sh` | remove the fixture, stop the stack if we started it | fixture, stack |

`env.sh` holds the shared settings (`VERIFY_BASE_URL`, `VERIFY_VITE_URL`, `VERIFY_EVIDENCE_ROOT` override them).
`fixture.php` runs inside the app image as `app` (UID 1001) through `docker compose run`, never as root.
