# Availability: network switches

The System admin switches a network off in Availability. Its Connect card disappears, scheduling a new post on it is
refused on web, REST and MCP alike (drafts are allowed), and a post whose time comes fails visibly with a reason the
Owner can retry once the network is back. Channels already connected stay connected, and background work keeps running.
The switches are database rows (`availability_switches`); a network is effective only when it is installed (env switch
plus credentials) and switched on.

## Customer-visible contract

- **Connect.** `GET /connect/{network}` and the confirmation/finish routes answer `403` while the network is off, and
  its card is left out of `connectablePlatforms`. A network the server has no credentials for is never listed either.
  Disconnecting an existing channel still works.
- **Scheduling.** Web (`app.posts.store`), REST (`POST /api/posts`) and MCP (`create-post-tool`) refuse a scheduled or
  publish-now post on a switched-off network with `rxdesk.availability.schedule_refused`, naming the network
  (`destinations.{i}.social_account_id` in a batch, `social_account_id` in a single write). A draft is accepted.
- **Publishing.** `PublishingGate` checks the post's platform before the Account's access. A due post ends
  `posts.status = failed`, `posts.publish_status = failed` and `posts.publish_block_reason = unavailable`; the value is
  `App\Enums\Account\AccessDenial::Unavailable`. `RetryBlockedPost` sends it back through the same gate, so a retry while
  the network is still off blocks again. `POST /posts/{post}/retry` and `posts:retry` work as for a Suspension.
- **The failure popover** shows `rxdesk.publishing_block.unavailable` (the network name filled in) and the Owner's
  **Try again** button (`@post-retry-<postId>`); the JSON API sends `publish_block_reason: "unavailable"`.
- **Banners.** The channel's publish page and the composer show `PlatformUnavailableBanner`
  (`@platform-unavailable-banner`, one line per network in `@platform-unavailable-<platform>`), fed by the shared
  `unavailablePlatforms` prop.
- **Internal API.** `GET /internal/v1/availability` lists one switch per network except X, ordered by the Platform enum:
  `{ key, kind: "platform", value, label, installed, switched_on, effective }`. `PATCH /internal/v1/availability/{key}`
  with `{ "enabled": bool }` flips it and answers the same row object. An unknown key answers `404`; switching on a
  network that is not installed answers `422`. Every PATCH writes an `availability.enable` or `availability.disable`
  audit row (even when the value did not change), with the admin email, `target_type = availability_switches` and
  `before` / `after` holding `{ enabled }`. The site token answers `404`.
- **Horizon.** The `social-publishing` supervisor listens to every platform queue (`Platform::allQueues()`), so a
  network's queue is still worked while it is switched off.

## How a user reaches it

The System admin switches LinkedIn off. The channels page no longer offers the LinkedIn card, and the composer warns
when the fixture channel is picked. A post scheduled before the switch fails when its time comes, naming LinkedIn, and
its popover offers **Try again**. Once the switch is back on, Try again re-queues it. The network call still fails on
the fixture's fake token; the proof is that `publish_block_reason` is cleared, not a published post.

## How to drive it (host sequence)

Needs `selling.sh up` (internal API tokens) and the fixture. The fixture channel is LinkedIn. **The selling overlay
gives the dev stack fake LinkedIn app credentials**, so Availability sees LinkedIn as installed and can switch it on
(without credentials the enable PATCH answers 422).

```sh
V=.claude/skills/verify-therxdesk/scripts

$V/launch.sh
$V/selling.sh up
$V/fixture.sh up
$V/complimentary.sh up

# Apply this branch's migrations: launch reuses an already-running stack.
( source "$V/env.sh"; app_run php artisan migrate --force )

# 1. With LinkedIn still on, schedule a post a few minutes ahead.
VERIFY_GATE_LEAD_MINUTES=8 $V/drive.sh gate-schedule
BLOCKED=$(python3 -I -c 'import json,sys; print(json.load(open(sys.argv[1]))["notes"]["blockedPostId"])' \
  ~/.cache/verify-therxdesk/runs/<utc>-gate-schedule/result.json)

# 2. Switch LinkedIn off at once, well before the scheduled time. LinkedIn has
#    two switchable platforms (Profile and Page); the connect card stands for the
#    network, so both go off to hide it.
$V/internal.sh PATCH /availability/platform:linkedin --body '{"enabled":false}'
#   HTTP 200, {"key":"platform:linkedin",...,"installed":true,"switched_on":false,"effective":false}
$V/internal.sh PATCH /availability/platform:linkedin-page --body '{"enabled":false}'
$V/internal.sh GET /availability --token admin        # no X row; 14 switches

# 3. Scheduling is refused on the public API (and the web/MCP surfaces).
CHANNEL=$($V/fixture.sh show | python3 -I -c 'import json,sys; print(json.load(sys.stdin)["channelId"])')
$V/api.sh POST /posts --body "{\"content\":\"Availability refusal\",\"status\":\"scheduled\",\"scheduled_at\":\"$(date -u -d '+2 days' +%Y-%m-%dT%H:%M:%SZ)\",\"social_account_id\":\"$CHANNEL\",\"content_type\":\"linkedin_post\"}"
#   HTTP 422, errors.social_account_id.0 = "LinkedIn is unavailable right now. Save this post as a draft, or try again once it's back."

# 4. Make the post due (or wait for its time), then let the scheduler try it:
#    the gate blocks it before any network call.
( source "$V/env.sh"
  app_run php artisan tinker --execute "App\\Models\\Post::whereKey('$BLOCKED')->update(['scheduled_at' => now()->subMinute()]);"
  app_run php artisan posts:process-scheduled )
$V/db.sh "select status, publish_status, publish_block_reason from posts where id = '$BLOCKED'"
#   status | publish_status | publish_block_reason
#   failed | failed         | unavailable

# 5. The UI proof: card hidden, composer banner and refusal, blocked popover.
VERIFY_BLOCKED_POST=$BLOCKED $V/drive.sh availability-off

# 6. Switch it back on and retry from the popover.
$V/internal.sh PATCH /availability/platform:linkedin --body '{"enabled":true}'
$V/internal.sh PATCH /availability/platform:linkedin-page --body '{"enabled":true}'
VERIFY_BLOCKED_POST=$BLOCKED $V/drive.sh availability-retry
$V/db.sh "select status, publish_status, publish_block_reason, error_message from posts where id = '$BLOCKED'"
#   publishing | pending | (null) | (null)          right after the click, or
#   failed     | failed  | (null) | <LinkedIn refusal>   once the fake token fails

$V/complimentary.sh down
$V/fixture.sh down
```

## Host-side proof

- `internal.sh GET /availability --token admin` lists 14 switches, no X, each `installed` / `switched_on` / `effective`;
  the LinkedIn row flips with the PATCH.
- `internal.sh GET /audit-log --token admin` holds an `availability.disable` and an `availability.enable` for each
  switch, with actor `verify-admin@example.com`, and `before` / `after` holding `{ "enabled": true|false }`.
- `api.sh POST /posts` answers `HTTP 422` with the refusal naming LinkedIn while the switch is off, and `HTTP 201` once
  it is back on.
- `db.sh` shows the due post `failed | failed | unavailable`, then `publish_block_reason` null after the retry.
- `drive.sh availability-off` screenshots: `connect-card-hidden`, `composer-unavailable`, `schedule-refused`,
  `blocked-post`. `drive.sh availability-retry`: `blocked-post`, `after-retry`, `retry-cleared`.

## Gotchas

- **Switch off within the scheduled lead** (8 minutes by default in the commands above) or the live scheduler publishes
  the post before the switch lands. Step 4 moves `scheduled_at` back so the scheduler acts at once; the alternative is
  waiting for the scheduled time. If the window is missed, switch back on, run `gate-schedule` again and switch off.
- **The fixture is LinkedIn.** The connect card and `/connect/linkedin` stay available while *either* variant is on,
  so the host sequence switches off both `platform:linkedin` and `platform:linkedin-page`. The Profile switch alone is
  what refuses a LinkedIn post; the Page switch is what hides the card.
- **The retry's network call is expected to fail.** The fixture channel carries a fake LinkedIn token; proof is
  `publish_block_reason` null after the retry, not a published post.
- **Restore both switches.** Leave `platform:linkedin` and `platform:linkedin-page` enabled
  (`--body '{"enabled":true}'`); other journeys schedule on the fixture channel.
- **Never print the token.** `internal.sh` and `api.sh` keep it in a mode-600 curl config.
