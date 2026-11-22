# Register a new Owner

A guest opens `/register` in selling mode, signs up with name, email and password, and lands at Welcome as a new
Owner. There is no Signup code: registration is upstream's open form (ticket #47 removed Phase 1's codes).

## Sub-features

- Guest form at `/register`: name, country, email, password (12+ characters, mixed case, a number, a symbol, not
  breached), with locale, timezone, week start and time format sent as hidden fields.
- The country is required in selling mode and stored on the User. The EU27, the EEA (Norway, Iceland,
  Liechtenstein), the UK, Jersey, Guernsey and the Isle of Man are refused with "Not available in the EU/UK yet";
  Switzerland and every other ISO 3166-1 country pass. Self-hosted `/register` collects no country.
- An invite link (`?invite=...`) still fills the invite, locks the email and verifies it at signup.
- No Signup code field, gate or `rxdesk:code` command (ticket #47).
- Selling mode: a new Owner lands on `/welcome`. Self-hosted `/register` still 404s without an invite (upstream).

## How to get to it (user POV)

`$V/selling.sh up`, then open `http://localhost:8000/register` signed out. Fill the form and press "Create account".

## Driving it with Playwright

- Journeys: `$V/drive.sh register` and `$V/drive.sh blocked-country` (both selling mode).
- Selectors: `@register-name`, `@register-country`, `@register-email`, `@register-password`,
  `@register-user-button`.
- Chrome: `@auth-preview-image` loads (a real app screenshot, no reviews), and the two links in
  `@legal-links` equal the `legal.terms` / `legal.privacy` shared props (the `LEGAL_*_URL` values).
- Checks: no `@register-signup-code` and no `input[name="signup_code"]`, and the page never says "Signup code";
  after submit the path starts with `/welcome`, `auth.user.email` is the new address and
  `auth.hasActiveSubscription` is `false`. The journey picks Thailand (`TH`) in `@register-country`.
- `blocked-country` picks Germany (`DE`), submits, stays on `/register`, sees "Not available in the EU/UK yet"
  and is not signed in.
- Side effects: `register` leaves one new `users` row, its `accounts` row and its first `workspaces` row, recorded
  as `signupEmail` in `result.json`; `blocked-country` leaves no row at all, recorded as `blockedSignupEmail`.
  `cleanup.sh` removes the signup (`fixture.sh guests`); from the host,
  `$V/db.sh "select count(*) from users where email = '<blockedSignupEmail>'"` must be `0`.

## Gotchas

- The journeys use a unique address per run (`verify-signup-<timestamp>@example.com`), so a second run does not
  fail the unique email check. `blocked-country` reuses the same pattern and creates nothing.
- The password rule calls the Pwned Passwords API (`uncompromised()`), so the run needs outbound network.
- In self-hosted mode `/register` 404s without an invite, so both journeys fail there by design.
