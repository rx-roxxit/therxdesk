# Admin alerts, heartbeat, capacity check and health

The System admin gets one email alert system: `[URGENT]` at once, `[RESOLVED]` when it clears,
`[TODAY]` at most once every 6 hours per condition, and `[DIGEST]` at 08:00 Asia/Bangkok only when
there is something to report. The scheduler dispatches a heartbeat job every 5 minutes, and a
capacity command every 5 minutes reads host RAM and load, the Horizon queue wait, free disk and the
Nightwatch burst. Nightwatch's webhook feeds the TODAY burst alert and the digest. `GET
/internal/v1/health` gives the admin app the figures. There is no UI. All three scheduled jobs run
only in selling mode (`routes/console.php`), and the commands themselves return success without doing
anything while self-hosted, so the selling overlay is required to exercise them.

## Alerts

| Level | When | De-duplication |
| --- | --- | --- |
| `[URGENT]` | free disk near the Disk guard threshold; an AI provider failure (`AiProviderFailure`, raised from the AI entry points) | once per open cycle |
| `[RESOLVED]` | an URGENT disk condition clears; the AI failure alert has no clear path, so it sends no RESOLVED mail | once per open cycle |
| `[TODAY]` | RAM > 75%, 5-minute load > 5, queue wait > 2 min, free disk < 30 GB, a Nightwatch burst (5 issues an hour) | one per condition per 6 hours |
| `[DIGEST]` | 08:00 Asia/Bangkok, only when non-empty | cleared TODAY alerts, new issues, yesterday's failed jobs, a due restore drill (a fresh install sends it daily until a drill is recorded) |

A free disk inside the Disk guard plus its 5 GB margin raises `[URGENT]` and suppresses the TODAY
disk alert, so the same run raises one or the other, never both.

Recipient: `RXDESK_ALERT_RECIPIENT` (default `admin@therxdesk.com`). In selling mode the dev stack
points it at the Mailpit inbox. Subjects are `[TAG] The RX Desk: <condition title>`. The endpoint is `POST /webhooks/nightwatch`, signed with
`NIGHTWATCH_WEBHOOK_SECRET` in the `Nightwatch-Signature` header (raw `hash_hmac('sha256', body, secret)`).
With no secret configured it answers 404.

## Proof checklist

Run everything from the dev machine, with the stack up. `$V` is
`.claude/skills/verify-therxdesk/scripts`, and `selling.sh up` also starts the local `heartbeat`
receiver, so the heartbeat pings `http://heartbeat:8080/hb`, never Better Stack.

1. `$V/selling.sh up` and `$V/mail.sh clear`.
2. Force a capacity alert. The run container gets the same selling overlay, so the command raises and
   emails. A load threshold of `0.001` raises TODAY, and a `100TB` Disk guard threshold (with no
   margin) makes free disk always look near the guard, so the same run raises URGENT:

   ```sh
   $V/selling.sh up
   $V/mail.sh clear
   source .claude/skills/verify-therxdesk/scripts/env.sh
   compose run --rm -T -u app \
       -e RXDESK_CAPACITY_LOAD=0.001 \
       -e RXDESK_CAPACITY_DISK_FREE=0GB \
       -e RXDESK_CAPACITY_DISK_URGENT_MARGIN=0GB \
       -e RXDESK_DISK_GUARD_THRESHOLD=100TB \
       app php artisan rxdesk:capacity
   ```

   `$V/mail.sh wait --to admin@therxdesk.com --subject '[TODAY]'` and
   `$V/mail.sh wait --to admin@therxdesk.com --subject '[URGENT]'` print the message ids, and
   `$V/mail.sh show <id>` shows the capacity body.
3. Clear the conditions and prove RESOLVED. Re-run `rxdesk:capacity` with healthy thresholds:

   ```sh
   compose run --rm -T -u app \
       -e RXDESK_CAPACITY_LOAD=1000 \
       -e RXDESK_CAPACITY_DISK_FREE=0GB \
       -e RXDESK_CAPACITY_DISK_URGENT_MARGIN=0GB \
       -e RXDESK_DISK_GUARD_THRESHOLD=1GB \
       app php artisan rxdesk:capacity
   ```

   `$V/mail.sh wait --to admin@therxdesk.com --subject '[RESOLVED]'`.
4. Health: `$V/internal.sh GET /health --token admin`. It answers `HTTP 200` with `queue`,
   `backup` (the last successful run, `null` until one exists), `restore_drill`, `disk` and
   `nightwatch` figures. [database-backups.md](database-backups.md) fills `backup` and
   `restore_drill`.
5. Heartbeat from the queue. Dispatch the job, run one worker, and read the receiver's log:

   ```sh
   compose run --rm -T -u app app php artisan tinker --execute='App\Jobs\PingHeartbeat::dispatch();'
   compose run --rm -T -u app app php artisan queue:work --once
   $V/selling.sh heartbeat 20
   ```

   The receiver log shows `GET /hb` from the app.
6. Nightwatch webhook, signed without ever printing the secret:

   ```sh
   compose run --rm -T -u app app php -r '
   $body = json_encode(["event" => "issue.opened", "payload" => ["issue" => [
       "id" => "verify-issue-1", "title" => "Verification issue", "type" => "exception",
       "status" => "open", "environment" => "production",
   ]]]);
   $signature = hash_hmac("sha256", $body, (string) getenv("NIGHTWATCH_WEBHOOK_SECRET"));
   $ch = curl_init("http://app/webhooks/nightwatch");
   curl_setopt_array($ch, [
       CURLOPT_POST => true,
       CURLOPT_POSTFIELDS => $body,
       CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Nightwatch-Signature: {$signature}"],
       CURLOPT_RETURNTRANSFER => true,
   ]);
   $response = curl_exec($ch);
   echo curl_getinfo($ch, CURLINFO_RESPONSE_CODE), "\n", $response, "\n";
   '
   ```

   It answers `200`; `$V/internal.sh GET /health --token admin` then lists the issue under
   `nightwatch.recent`. An unsigned request answers 401, and a wrong signature records nothing.
7. `$V/selling.sh down` returns the stack to self-hosted mode (and removes the heartbeat receiver).

Never point the dev stack at a real Better Stack heartbeat or the real Nightwatch webhook.
