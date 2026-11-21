# Internal API (`/internal/v1`)

A token-gated API in the main app for the admin app and the website. There is no UI. The dev stack gets it in
selling mode.

## Tokens and access

- `RXDESK_INTERNAL_ADMIN_TOKEN`: read-write, every internal route.
- `RXDESK_INTERNAL_SITE_TOKEN`: read-only, only `GET /site`.
- With neither set, every `/internal` route answers 404. A missing or wrong `Authorization: Bearer ...` answers
  404 too, so the API is indistinguishable from an absent one.
- Admin routes also require `X-Internal-Admin-Email` (a valid email); a missing or invalid one answers 422.

`selling.sh up` generates both tokens into `~/.cache/verify-therxdesk/state/internal-api.env` (mode 600) and never
prints them. `selling.sh down` removes the file.

## Endpoints

- `GET /internal/v1/site` (site or admin token): top-level `{ "plans": [...] }`. Each Plan has `slug`, `name`,
  `quota_bytes`, `ai_credits_per_month` and `monthly` / `yearly` in USD cents. Only active Plans, by `sort`.
- `GET /internal/v1/accounts?search=<email>` (admin): paginated `{ "data": [...], "links": {...}, "meta": {...} }`,
  the configured page size (25). A row has `id`, `name`, `owner` (`id`, `name`, `email`), `plan` (`id`, `slug`,
  `name`), `subscription_status`, `members_count` and `created_at`. `search` matches any User email in the Account.
- `GET /internal/v1/accounts/{uuid}` (admin): the Customer detail, top level. `id`, `name`, `created_at`, `owner`
  (`id`, `name`, `email`, `email_verified_at`), `access_state` (null until its source exists), `plan` (`id`, `slug`,
  `name`, `quota_bytes`, `ai_credits_per_month`), `subscription` (`status`, `interval`, `amount` in cents,
  `trial_ends_at`, `ends_at`, `created_at`, or null), `storage` (`used_bytes`, `quota_bytes`), `members_count`,
  `recent_posts` (the 10 newest; `status`, `publish_status`, `error_message`, `platform`, timestamps, never
  content) and `lifecycle_emails` (null until the email log exists).
- `GET /internal/v1/audit-log` (admin): paginated, newest first. A row has `id`, `actor`, `action`, `target_type`
  (the table), `target_id`, `before`, `after` and `created_at`. Every internal write and every CLI write records
  one (the CLI actor is `cli`); a password or secret key is never stored.

## Helper

```sh
$V/internal.sh <METHOD> <path> [--token admin|site|none|bogus] [--email <email>] [--body <json>]
```

It reads the generated tokens from the state file, prints `HTTP <status>` and the pretty-printed body, and never
prints a token. `--token admin` (the default) also sends `X-Internal-Admin-Email`, defaulting to
`verify-admin@example.com`. `--token none` sends no Authorization header; `--token bogus` sends a made-up one.
Paths may be written with or without the `/internal/v1` prefix.

```sh
$V/internal.sh GET /site --token site
$V/internal.sh GET /accounts --token admin
$V/internal.sh GET '/accounts?search=verify-agent@example.com' --token admin
$V/internal.sh GET /accounts/<uuid> --token admin
$V/internal.sh GET /audit-log --token admin
```

## Proof checklist

- `--token none` and `--token bogus`: `HTTP 404` on `/site`, `/accounts` and `/audit-log`.
- `--token site`: `HTTP 200` on `/site`, `HTTP 404` on `/accounts` and `/audit-log`.
- `--token admin` without `--email`: `HTTP 422`; with the email: `HTTP 200`.
- After `php artisan rxdesk:quota set <owner email> 20GB`, `/audit-log` shows the `account.quota.set` row with
  actor `cli` and its `before` / `after`.
