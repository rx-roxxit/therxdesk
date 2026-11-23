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

- `GET /internal/v1/site` (site or admin token): top-level `{ "offer": {...}|null, "plans": [...] }`. Each Plan has
  `slug`, `name`, `quota_bytes`, `ai_credits_per_month`, `monthly` / `yearly` in USD cents and `offer.monthly` /
  `offer.yearly` (`regular`, `discount`, `discounted` in cents, or null). `offer` is the running general Offer
  (never a link one) with its name, discount, `trial_days`, dates, `plan_ids` and `intervals`. Only active Plans, by
  `sort`.
- `GET /internal/v1/accounts?search=<email>` (admin): paginated `{ "data": [...], "links": {...}, "meta": {...} }`,
  the configured page size (25). A row has `id`, `name`, `owner` (`id`, `name`, `email`), `plan` (`id`, `slug`,
  `name`), `subscription_status`, `members_count` and `created_at`. `search` matches any User email in the Account.
- `GET /internal/v1/accounts/{uuid}` (admin): the Customer detail, top level. `id`, `name`, `created_at`, `owner`
  (`id`, `name`, `email`, `email_verified_at`), `access_state` (null until its source exists), `plan` (`id`, `slug`,
  `name`, `quota_bytes`, `ai_credits_per_month`), `subscription` (`status`, `interval`, `amount` in cents,
  `trial_ends_at`, `ends_at`, `created_at`, or null), `complimentary` (`in_force`, `since`, `until`, or null until one
  was ever given), `storage` (`used_bytes`, `quota_bytes`), `members_count`,
  `recent_posts` (the 10 newest; `status`, `publish_status`, `error_message`, `platform`, timestamps, never
  content) and `lifecycle_emails` (null until the email log exists).
- `POST /internal/v1/accounts/{uuid}/complimentary` (admin): `plan_id` (any Plan, archived included) and an optional
  `ends_at` (after now, at most `2037-12-31`). Gives the Plan outside Stripe: access, Quota and AI credits follow it at
  once. Answers `{ "id", "plan": { "id", "slug", "name" }, "complimentary": { "since", "until", "in_force" } }`. An
  Account whose `blocked_country_at` is set or that has an active Subscription answers 422 and nothing changes.
- `POST /internal/v1/accounts/{uuid}/complimentary/end` (admin): an optional `ends_at`, defaulting to 30 days ahead;
  an earlier date is allowed, at most `2037-12-31`. When the date passes the Account is Lapsed. An Account that never
  had a Complimentary Plan answers 422.
- `GET /internal/v1/availability` (admin): `{ "switches": [...] }`, one row per network except X, in the
  `Platform` enum order. A row is `key` (`platform:<value>`), `kind` (`platform`), `value`, `label`, `installed`,
  `switched_on` and `effective`; a missing row counts as switched on, and a network is effective only while it is
  installed (env switch and credentials) and switched on.
- `PATCH /internal/v1/availability/{key}` (admin): `{ "enabled": bool }`, answers the row object. An unknown key is
  `404`; switching on a network the server is not installed answers `422`. Every PATCH records
  `availability.enable` or `availability.disable` (even when the value did not change), with `target_type`
  `availability_switches`, `target_id` the row's id and `before` / `after` holding `{ "enabled": bool }`. The site
  token answers `404`.
- `GET /internal/v1/audit-log` (admin): paginated, newest first. A row has `id`, `actor`, `action`, `target_type`
  (the table), `target_id`, `before`, `after` and `created_at`. Every internal write and every CLI write records
  one (the CLI actor is `cli`); a password or secret key is never stored.
- `GET /internal/v1/health` (admin): `queue` (`available`, `waits`), `backup` (`last_run_at`, null until a run),
  `restore_drill` (`last_performed_at`, `due_at`, `due`), `disk`, and `nightwatch` (`open`, `recent`). The site
  token answers 404.
- `GET /internal/v1/operator` (admin): `{ "current": {...}|null, "history": [...] }`. A row has `id`,
  `legal_name_th`, `legal_name_latin`, `address_th`, `address_latin`, `tax_id`, `vat_id`, `contact_email`, `phone`,
  `effective_from`, `ended_at`, `is_current`, `created_at` and `updated_at`. `history` holds the ended rows,
  newest `effective_from` first.
- `POST /internal/v1/operator` (admin): `legal_name_th`, `legal_name_latin`, `address_th`, `address_latin`, `tax_id`
  (13 digits), `contact_email`, optional `vat_id`, `phone` and `effective_from` (not in the future). Creates the
  first row, or ends the current row at `effective_from` (default now) and creates the next. `201` with the
  overview. `ended_at` is never accepted.
- `PATCH /internal/v1/operator` (admin): any of the same fields (`ended_at` never). Edits the current row and
  answers `200` with the overview; no current row answers `422`.
- The site token answers `404` on every `/operator` endpoint. Every write records `operator.create` /
  `operator.update` with the admin email.
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
- `GET /internal/v1/offers` (admin): paginated, newest start first; `?search=` matches name or slug, `?reach=general|link`,
  `?ended=1|0`. A row has `id`, `slug`, `name`, `reach`, `discount` (`type`, `percent_off`, `amount_off`, `currency`,
  `duration`, `duration_in_months`, or null), `stripe_coupon_id`, `trial_days`, `starts_at`, `ends_at`,
  `max_redemptions`, `show_banner`, `plan_ids`, `intervals`, `is_ended`, `redemptions_count` and timestamps.
- `POST /internal/v1/offers` (admin): `slug` (immutable), `name`, `reach` (`general` or `link`), `coupon`
  (`type` percent/amount, `percent_off` or `amount_off` in USD cents, `duration` once/repeating/forever,
  `duration_in_months` when repeating; optional), `trial_days`, `starts_at`, `ends_at`, `max_redemptions`,
  `show_banner`, `plan_ids` (default every active Plan), `intervals` (default `["monthly"]`). It needs a coupon, trial
  days, or both. The app creates the Stripe coupon with `applies_to` the covered Plans' products and a stable id
  (`rxdesk-offer-<hash of the slug>`), so a retried create or a rerun reuses it. 201.
- `PATCH /internal/v1/offers/{uuid}` (admin): only `name`, `starts_at`, `ends_at`, `max_redemptions`, `show_banner`
  and `trial_days`. Sending `slug`, `reach`, `coupon`, `percent_off`, `amount_off`, `duration`, `duration_in_months`,
  `plan_ids` or `intervals` answers 422 (the discount, duration and coverage are locked). An ended Offer answers 422.
- `POST /internal/v1/offers/{uuid}/end` (admin): ends the Offer now; a second call is a no-op and records nothing.
  Offers are never deleted and an ended Offer is read-only.
- A Stripe failure answers `502` and saves nothing. Every write above that changes something records an audit row
  with the admin email from `X-Internal-Admin-Email` (`offer.create`, `offer.update`, `offer.end`,
  `account.complimentary.give`, `account.complimentary.end`).

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
$V/internal.sh GET /availability --token admin
$V/internal.sh PATCH /availability/platform:linkedin --token admin --body '{"enabled":false}'
$V/internal.sh PATCH /availability/platform:linkedin --token admin --body '{"enabled":true}'
$V/internal.sh GET /offers --token admin
$V/internal.sh POST /offers --token admin --body '{"slug":"spring-sale","name":"Spring sale","reach":"general","coupon":{"type":"percent","percent_off":20,"duration":"repeating","duration_in_months":3},"trial_days":14,"starts_at":"2026-11-22T00:00:00Z","show_banner":true}'
$V/internal.sh PATCH /offers/<uuid> --token admin --body '{"name":"Spring sale extended","show_banner":false}'
$V/internal.sh POST /offers/<uuid>/end --token admin
$V/internal.sh POST /accounts/<uuid>/complimentary --token admin --body '{"plan_id":"<uuid>"}'
$V/internal.sh POST /accounts/<uuid>/complimentary/end --token admin --body '{"ends_at":"2037-12-31"}'
$V/internal.sh GET /operator --token admin
$V/internal.sh POST /operator --token admin --body '{"legal_name_th":"บริษัท ทดสอบ จำกัด","legal_name_latin":"Test Operator Co., Ltd.","address_th":"123 ถนนทดสอบ กรุงเทพมหานคร 10110","address_latin":"123 Test Road, Bangkok 10110","tax_id":"1234567890123","contact_email":"billing@example.com"}'
$V/internal.sh PATCH /operator --token admin --body '{"phone":"555-0100"}'
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
- A `POST /offers` returns 201 and `/audit-log` has `offer.create` with the admin email; the same request reuses the
  coupon with the same slug, and a `PATCH` with `coupon`, `plan_ids` or `reach` answers 422.
- `--token site` on any of the write endpoints answers 404.
- `GET /availability` lists 14 switches (no X) with `installed`, `switched_on` and `effective`; a PATCH off answers
  the row with `switched_on: false` and `effective: false`, and `/audit-log` shows `availability.disable` with the
  admin email. Enabling a network the server has no credentials for answers 422, and an unknown `platform:…` key
  answers 404.
- `GET /health` with the admin token answers 200 with `queue`, `backup`, `restore_drill`, `disk` and `nightwatch`;
  with the site token it answers 404.
- `POST /operator` returns 201 and `/audit-log` has `operator.create` with the admin email; a `PATCH` records
  `operator.update` with the before/after values. A second `POST` ends the previous row and `/operator` answers
  exactly one `current` plus the previous row in `history`. A future `effective_from` answers 422; the site token
  answers 404.
