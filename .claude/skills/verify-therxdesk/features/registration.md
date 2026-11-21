# Register a new Owner

A guest opens `/register` in selling mode, signs up with name, email and password, and lands at Welcome as a new
Owner. There is no Signup code: registration is upstream's open form (ticket #47 removed Phase 1's codes).

## Sub-features

- Guest form at `/register`: name, email, password (12+ characters, mixed case, a number, a symbol, not breached),
  with locale, timezone, week start and time format sent as hidden fields.
- An invite link (`?invite=...`) still fills the invite, locks the email and verifies it at signup.
- No Signup code field, gate or `rxdesk:code` command (ticket #47).
- Selling mode: a new Owner lands on `/welcome`. Self-hosted `/register` still 404s without an invite (upstream).

## How to get to it (user POV)

`$V/selling.sh up`, then open `http://localhost:8000/register` signed out. Fill the form and press "Create account".

## Driving it with Playwright

- Journey: `$V/drive.sh register` (selling mode).
- Selectors: `@register-name`, `@register-email`, `@register-password`, `@register-user-button`.
- Chrome: `@auth-preview-image` loads (a real app screenshot, no reviews), and the two links in
  `@legal-links` equal the `legal.terms` / `legal.privacy` shared props (the `LEGAL_*_URL` values).
- Checks: no `@register-signup-code` and no `input[name="signup_code"]`, and the page never says "Signup code";
  after submit the path starts with `/welcome`, `auth.user.email` is the new address and
  `auth.hasActiveSubscription` is `false`.
- Side effect: one new `users` row, its `accounts` row and its first `workspaces` row. The address is recorded as
  `signupEmail` in `result.json`; `cleanup.sh` removes them (`fixture.sh guests`).

## Gotchas

- The journey uses a unique address per run (`verify-signup-<timestamp>@example.com`), so a second run does not
  fail the unique email check.
- The password rule calls the Pwned Passwords API (`uncompromised()`), so the run needs outbound network.
- In self-hosted mode `/register` 404s without an invite, so the journey fails there by design.
