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
- `GET /internal/v1/health` (admin): `queue` (`available`, `waits`), `backup` (`last_run_at`, null until a run),
  `restore_drill` (`last_performed_at`, `due_at`, `due`), `disk`, and `nightwatch` (`open`, `recent`). The site
  token answers 404.
- `GET /internal/v1/plans` (admin): paginated, ordered by `sort` then `name`; `?search=` matches name or slug,
  `?archived=1|0|all` (default all). A row has `id`, `slug`, `name`, `quota_bytes`, `ai_credits_per_month`,
  `sort`, `is_archived`, `stripe_product_id`, `prices.monthly` and `prices.yearly` (each the active row as
  `{id, amount, stripe_price_id, is_active}`, or null) and timestamps.
- `POST /internal/v1/plans` (admin): `slug` (lowercase, immutable), `name`, `quota_bytes`,
  `ai_credits_per_month`, `monthly_amount` in USD cents and `sort`. 201. The app creates the Stripe Product and
  the monthly Price plus the yearly Price at ten times monthly, then stores their ids in `plan_prices`.
- `PATCH /internal/v1/plans/{uuid}` (admin): the same fields without `slug` (`slug` is `prohibited`, so sending it
  answers 422). A changed `monthly_amount` creates new Prices and archives the old ones in Stripe and in
  `plan_prices`; an unchanged amount touches no Price. An archived Plan answers 422.
- `POST /internal/v1/plans/{uuid}/archive` (admin): archives the Plan, its Product and its active Prices. A second
  archive is a no-op and records no audit row.
- `POST /internal/v1/plans/{uuid}/quota-cut-dry-run` (admin): `{ "quota_bytes": <int> }` in; answers
  `{ "quota_bytes", "owners": [{ "account_id", "owner_name", "owner_email", "used_bytes" }] }`, the Owners on that
  Plan, without their own override, whose Storage used is over the proposed Quota.
- `GET` / `PATCH /internal/v1/catalog-settings` (admin): `trial_days`, `tokens_per_credit`, `trial_ai_credits`
  (defaults 7, 150, 1,000); `GET` also returns `updated_at`. `trial_ai_credits` may not exceed any active Plan's
  monthly credits, and a Plan's credits may not fall below it.
- A Stripe failure answers `502` and saves nothing. Every write above that changes something records an audit row
  with the admin email from `X-Internal-Admin-Email`.

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
$V/internal.sh GET /plans --token admin
$V/internal.sh POST /plans --token admin --body '{"slug":"verify-plan","name":"Verify","quota_bytes":1073741824,"ai_credits_per_month":2000,"monthly_amount":990,"sort":9}'
$V/internal.sh PATCH /plans/<uuid> --token admin --body '{"monthly_amount":1090}'
$V/internal.sh POST /plans/<uuid>/archive --token admin
$V/internal.sh POST /plans/<uuid>/quota-cut-dry-run --token admin --body '{"quota_bytes":536870912}'
$V/internal.sh GET /catalog-settings --token admin
$V/internal.sh PATCH /catalog-settings --token admin --body '{"trial_days":14}'
$V/internal.sh GET /health --token admin
```

## Proof checklist

- `--token none` and `--token bogus`: `HTTP 404` on `/site`, `/accounts` and `/audit-log`.
- `--token site`: `HTTP 200` on `/site`, `HTTP 404` on `/accounts` and `/audit-log`.
- `--token admin` without `--email`: `HTTP 422`; with the email: `HTTP 200`.
- After `php artisan rxdesk:quota set <owner email> 20GB`, `/audit-log` shows the `account.quota.set` row with
  actor `cli` and its `before` / `after`.
- A `POST /plans` returns 201; `GET /plans` shows the new `stripe_product_id` and both Price ids; `stripe.sh get
  /v1/products/<id>` and `stripe.sh get /v1/prices/<id>` answer those objects; `db.sh` shows the two active
  `plan_prices` rows; `/audit-log` has `plan.create` with the admin email.
- A `PATCH` that changes `monthly_amount` returns 200; the old sandbox Prices answer `active=false`; the old
  `plan_prices` rows are `is_active=false` and still carry their `stripe_price_id` (they are never deleted).
- The 502 path (a Stripe rejection saves nothing) is covered by `tests/Feature/Internal/PlansTest.php` with a faked
  Stripe. A live request cannot provoke it: `monthly_amount` is validated to `1..99999999`, and Stripe accepted the
  yearly ten-times amount even at that ceiling (checked November 2026).
- `--token site` on any of the write endpoints answers 404.
- `GET /health` with the admin token answers 200 with `queue`, `backup`, `restore_drill`, `disk` and `nightwatch`;
  with the site token it answers 404.
