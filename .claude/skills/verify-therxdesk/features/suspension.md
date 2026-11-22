# Suspension and the publishing gate

The System admin turns a whole Account off with a Suspension. Every member loses the app at once, with no path back in
until it is lifted, and a post the scheduler tries meanwhile never reaches the network. This replaces the product's
"Active subscription required." dead end for a customer who must be stopped (issue #61).

## Customer-visible contract

- **The Suspended screen.** `GET /welcome/suspended` renders `welcome/Suspended`: the page root
  `@welcome-suspended`, the banner `@suspended-banner`, the sentence **Account suspended, contact support**, and a
  support action (`mailto:`). `EnsureAccountReady` sends the Owner and every Member there from any app page;
  `/welcome/plan`, `/welcome/plan/portal` and `/welcome/subscription-required` redirect there too. Once the Suspension
  is lifted the same route forwards the user where they belong (the app with access, the plan step, or the member
  hold screen).
- **The API.** Any `/api` route answers `402` with `{"message":"Account suspended, contact support."}`, from
  `LoadWorkspaceFromToken`, before the subscription check. An API client sees the same wall a browser does.
- **The publishing gate.** `PublishPost` and `PublishToSocialPlatform` call `PublishingGate` before any network call.
  A blocked post ends `posts.status = failed`, `posts.publish_status = failed` and
  `posts.publish_block_reason = suspended`; a Lapsed Account gets `lapsed` instead. The column holds the code
  (`App\Enums\Account\AccessDenial`) and is translated only when shown. Any real network attempt clears it.
- **The failure popover.** A blocked post shows its reason in `@post-failure-<postId>` →
  `@post-failure-reason-<postId>`, and a **Try again** button `@post-retry-<postId>` when the signed-in member may
  publish directly (the Owner always may). `POST /posts/{post}/retry` (`RetryBlockedPost`) sends the post back
  through the gate: still suspended or lapsed, it blocks again.
- **Internal API.** `POST /internal/v1/accounts/{uuid}/suspension` with `{"reason":"..."}` writes
  `accounts.suspended_at` + `accounts.suspension_reason` and records `account.suspend`;
  `POST .../suspension/lift` clears both and records `account.lift` (a lift with nothing to lift answers 422). The
  reason is internal only (3–255 characters), hidden from the model and every customer surface, and kept in the audit
  entry. `GET /internal/v1/accounts/{uuid}` carries `access_state` (`has_app_access`, `lapsed`, `suspended`,
  `suspended_at`, `suspension_reason`).

## How a user reaches it

The System admin suspends the Account (the admin app's action, here `suspension.sh up`). The next app page any
member opens lands on the Suspended screen, and the scheduler refuses their posts. When the admin lifts it
(`suspension.sh lift`), a reload returns the app and the Owner can retry a blocked post from its failure popover.

## How to drive it (host sequence)

Needs `selling.sh up` (the internal API tokens and the buyer mode) and a fixture. The gate journeys need an Account
with app access; the existing Complimentary Plan (`complimentary.sh up`) gives the fixture access without Stripe.

```sh
$V/launch.sh
$V/selling.sh up                  # Plans, internal tokens, Stripe sandbox
$V/fixture.sh up                  # Owner + Workspace + LinkedIn channel + Member; prints memberEmail
$V/complimentary.sh up            # app access for gate-schedule / gate-retry

# 1. Schedule a post ~5 minutes ahead, while the Account can still use the app.
$V/drive.sh gate-schedule
# result.json -> notes.blockedPostId is the post to watch; notes.scheduledAt is its time.

# 2. Suspend at once, before the scheduled time: the dev scheduler runs every minute.
$V/suspension.sh up

# 3. The Owner and the Member both hit the Suspended screen; the API is refused.
$V/drive.sh suspension
$V/api.sh GET /posts              # HTTP 402, "Account suspended, contact support."

# 4. Tick the scheduler (or wait for the every-minute one): the gate blocks the post.
( source "$V/env.sh"; app_run php artisan posts:process-scheduled )
$V/db.sh "select status, publish_status, publish_block_reason from posts where id = '<blockedPostId>'"
#   status | publish_status | publish_block_reason
#   failed | failed         | suspended

# 5. Lift the Suspension, then retry the blocked post in the UI.
$V/suspension.sh lift
VERIFY_BLOCKED_POST=<blockedPostId> $V/drive.sh gate-retry
$V/db.sh "select status, publish_status, publish_block_reason, error_message from posts where id = '<blockedPostId>'"
#   publishing | pending | (null) | (null)      right after the click, or
#   failed     | failed  | (null) | <LinkedIn refusal>   once the fake token fails

$V/db.sh "select action, actor, before, after from audit_logs where target_id = '<accountId>' order by created_at"
$V/suspension.sh down
$V/complimentary.sh down
$V/fixture.sh down
```

`drive.sh gate-retry` reads the id from `VERIFY_BLOCKED_POST`; take it from the gate-schedule evidence:

```sh
python3 -I -c 'import json,sys; print(json.load(open(sys.argv[1]))["notes"]["blockedPostId"])' \
  ~/.cache/verify-therxdesk/runs/<utc>-gate-schedule/result.json
```

## Host-side proof

- `suspension.sh show` prints the fixture Account's `access_state`:
  `{"has_app_access": false, "lapsed": …, "suspended": true, "suspended_at": "...", "suspension_reason": "..."}`
  while suspended, and `"suspended": false` after the lift.
- `api.sh GET /posts` answers `HTTP 402` with the Suspension message while suspended, and `HTTP 200` after the lift
  (the fixture is Complimentary, so it has access). The token is issued by `fixture.php token` and never printed.
- `audit_logs` holds one `account.suspend` and one `account.lift` row for the Account, actor
  `verify-admin@example.com` (the internal helper's email), each with `before` / `after` holding `suspended_at` and
  `suspension_reason`.
- The screenshots: `owner-suspended`, `member-suspended`, `blocked-post` (the popover with the suspended reason),
  `after-retry`, `retry-cleared`.

## Gotchas

- **Order matters.** Suspend within the scheduled lead (5 minutes by default) or the live scheduler publishes the
  post to the network instead of blocking it. If the window is missed, `suspension.sh lift`, run `gate-schedule`
  again, then `suspension.sh up`. `VERIFY_GATE_LEAD_MINUTES=8 drive.sh gate-schedule` widens the lead.
- **The gate journeys need app access.** In selling mode an Owner without a Plan lands on `/welcome/plan` and the
  journey stops with `the fixture has no app access ... run complimentary.sh up`. The Complimentary Plan stays in
  force across the Suspension, so the retry after the lift reaches the network.
- **The retry's network call is expected to fail.** The fixture channel carries a fake LinkedIn token. The proof is
  `publish_block_reason` null after the retry (the access block is gone), not a published post.
- **`fixture.sh up` recreates the Member and the API token too.** `fixture.sh down` / `cleanup.sh` remove the Owner
  and the Member with the Account.
- **Never print the token.** `fixture.sh token` refuses a terminal and writes nothing to logs; `api.sh` keeps it in a
  mode-600 curl config. The internal token stays inside `internal.sh`.
- A Suspension outranks a Lapse and self-hosted mode: the reason column is `suspended` even when the Account is also
  Lapsed, and the 402 comes before the subscription check.
