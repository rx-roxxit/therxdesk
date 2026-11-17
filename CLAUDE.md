> **Start here:** The RX Desk's scope and decisions live in `idea.md`, and its vocabulary in `GLOSSARY.md`. Read both before planning or changing anything. `AGENTS.md` below describes upstream TryPost (much of it Cloud-only: billing, offers, PostHog). Use it as coding rules, not as the scope of this project.

@AGENTS.md

## The RX Desk overrides

The RX Desk is a fork of TryPost. Everything in `AGENTS.md` and `.ai/rules` applies, except where this section says otherwise. When the two disagree, this section wins.

### New copy is English only (owner decision November 2026)

This overrides the 16-locale rules in `AGENTS.md` ("Writing in-app copy", "Translated copy must fit its UI slot", "Emails"), but only for copy The RX Desk adds:

- Every new user-facing string (UI, errors, toasts, emails) is written in English only, in its own file, `lang/<locale>/rxdesk.php`. No RX Desk key goes into an upstream lang file.
- The file exists in all 16 locale folders with the same keys, and every locale holds the English text. `LocalizationParityTest` therefore stays green with no test change, the frontend never shows a missing key, and upstream merges never conflict, because upstream never touches `rxdesk.php`.
- Never translate these strings, and never add RX Desk keys to `tests/fixtures/single-line-copy.php`.
- Upstream strings stay as upstream ships them.
- Everything else about copy still applies: go through `__()` / `$t`, never write a literal, and build emails in Maizzle.

### Running commands (Docker on the VPS)

Nothing is installed on the host: PHP, Composer and Node live in the dev image, and every command runs in a one-off container as `app` (UID 1001, the host user `rxdev`). This overrides the Herd section of `AGENTS.md`, which does not apply here.

```sh
R="docker compose run --rm -T -u app app"

$R php artisan test --compact tests/Feature/Auth/RegistrationTest.php   # one file (or --filter=name)
$R php artisan test --compact --parallel --processes=4                  # full suite (Unit + Feature)
$R npm run build                                                        # Vite build
$R vendor/bin/pint --dirty --format agent                               # format PHP
$R php artisan make:test --pest SomeFeatureTest                         # any artisan / composer / npm command
```

- `run` starts only pgsql and redis and skips the entrypoint's bootstrap, so nothing writes into the checkout as root. Never run these through `docker compose exec app` or without `-u app`: files made as root (a `make:` class, `lang/php_*.json`, Wayfinder output) cannot be edited from the host and break the next `app` run.
- `compose.override.yaml` binds every dev port to 127.0.0.1 (Docker-published ports skip ufw) and sets UID/GID 1001. Keep it that way.
- Tests use the `trypost_test` database (`phpunit.xml`, `.env.testing`) in the dev `pgsql` container of this compose project. The dev stack never shares a container, volume or database with production, and tests are never run against the production compose file.
- `docker compose up -d` starts the full dev app (web on 127.0.0.1:8000, reach it with `ssh -L 8000:127.0.0.1:8000`). Upstream's container runs as root, so after `docker compose stop app` hand the generated files back before the next `app` command:
  `docker compose run --rm --no-deps app chown -R 1001:1001 lang resources/js public storage bootstrap/cache vendor node_modules .env`
- Baseline (November 2026): the full suite takes about 13 minutes and passes except `BillingControllerTest` › "an amount discount is formatted in the user's language". Upstream's Alpine image ships English ICU data only (no `icu-data-full`), so `pt-BR` currency formatting falls back to `$18.00`. Billing is off when self-hosted; do not chase this failure.
- After `docker compose down -v` (volumes wiped): `docker compose run --rm --no-deps app chown 1001:1001 vendor node_modules`, then `$R composer install` and `$R npm ci`.

### The brand (The RX Desk, not TryPost)

- Upstream lang files say "TryPost". After every upstream merge, run `$R php artisan rxdesk:rebrand-lang` and commit the result. It rewrites values only, never keys, and running it twice changes nothing. `tests/Feature/RxDesk/LangBrandTest.php` fails while any locale still says TryPost.
- Upstream tests that pin a translated string containing the brand (e.g. `DeleteAccountCopyTest`) or its length (`tests/fixtures/single-line-copy.php`: "The RX Desk" is 4 characters longer) are updated to match after the rewrite.
- The mark ("RX" on a rounded square) is defined once in `resources/js/lib/rxMark.ts` (`AppLogo.vue`, the share card). `public/favicon.svg` repeats its path; the PNG/ICO icons were rasterized from it with ImageMagick in the dev container (no SVG decoder there, so draw the path with `magick -draw`).
- Email header and footer are `maizzle/components/{header,footer}.html`. Rebuild with `$R sh -c 'cd maizzle && npm ci && npm run build'`, never by editing `resources/views/mail`.
- App name, mail-from name, legal links (the fork's README) and the disabled changelog come from env (`docker/.env.docker.example` for dev). Production build args are set by the production compose file.

### Signup (Signup codes, not open registration)

- Self-hosted `/register` opens without an Invite (`EnsureRegistrationEnabled` no longer 404s). A signup without an Invite needs a Signup code (`SignupCode::isRequiredFor()`): `RegisterRequest` checks it, and `CreateOwnerWithSignupCode` locks the code row and consumes it in the same transaction as `CreateUser`. Never consume a code anywhere else.
- Google and GitHub signup stay off (`GOOGLE_AUTH_ENABLED` / `GITHUB_AUTH_ENABLED` false), and their callbacks still require an Invite (`PreservesInvite`); `tests/Feature/RxDesk/OAuthSignupGateTest.php` guards it. Turning OAuth signup on means adding the code to that flow first.
- The System admin manages codes with `$R php artisan rxdesk:code create --note="..."`, `rxdesk:code list` and `rxdesk:code revoke <code>`.

### Video policy (what happens to a post's videos after it publishes)

- The Owner picks it on Preferences (`accounts.video_policy`: `keep` default, `delete`, `backup`; `PATCH app.settings.video-policy.update`, Owner only through `AccountPolicy::update`). The request accepts `VideoPolicy::selectable()`; `backup` joins it only while the Owner has a working Drive connection (`VideoPolicy::selectable(DriveConnection)`).
- `ScheduleVideoPolicy` (listener on `PostStatusChanged`) queues `ApplyVideoPolicy` one hour after a post moves into `published`; nothing else starts it. The job reads the policy when it runs, and `DeletePostVideos` re-reads the post under its lock and acts only while it is still published, so failed, retrying, pending and pending-review posts keep their files for `posts:retry`.
- **Google Drive for Backup** (Preferences, Owner only; `DriveBackupController`): a full-page offline sign-in on the existing Google media client (`GoogleMediaOAuth::authorizeOfflineUrl()`: `drive.file` only, `access_type=offline`, `prompt=consent`, never `include_granted_scopes`). The refresh token lives encrypted in `media_source_connections` under `Source::GoogleDriveBackup`, one row for the Owner, apart from the composer picker's sign-in (which is never stored). `Account::driveConnection()` reads it (`none` / `connected` / `needs_reconnect`, shared as the `driveConnection` Preferences prop); disconnecting returns a Backup policy to Keep. When Google rejects the token, call `MarkDriveNeedsReconnect::execute($account)`: Preferences then asks to reconnect and Backup is refused until the Owner signs in again.
- **Setup:** the backup sign-in has its own redirect URI, so register it on the same Google OAuth client beside the picker's: `<APP_URL>/settings/preferences/drive/callback` (env `GOOGLE_MEDIA_BACKUP_REDIRECT`, in `.env.example` and `docker/.env.docker.example`). Client id and secret are the existing `GOOGLE_MEDIA_CLIENT_ID` / `GOOGLE_MEDIA_CLIENT_SECRET`. Google API hosts stay in `config/trypost.php` (`media_sources.google_oauth`, `google_drive`).
- Videos are removed only through `SyncOwnedMedia` (which hands them to `DeleteOwnedMedia`), never by deleting files or rows directly, so `posts.media` and the thread replies stop listing them. Each removed video leaves a `video_records` row, shown in the post details by `PostVideoRecords.vue` (the card queries eager-load `videoRecords`).

## Agent skills

### Issue tracker

Issues and specs live in GitHub Issues on rx-roxxit/therxdesk (public fork; never put secrets, server IPs or credentials in issues). See `docs/agents/issue-tracker.md`.

### Triage labels

Default five-role vocabulary (needs-triage, needs-info, ready-for-agent, ready-for-human, wontfix). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
