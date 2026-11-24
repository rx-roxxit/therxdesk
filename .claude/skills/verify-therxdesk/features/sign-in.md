# Sign in

A user with an email and password signs in and lands on the Schedule of their current Workspace, in self-hosted
mode. In selling mode the landing depends on access: an Owner without it is sent first to `/welcome/plan`, which
forwards to the first unfinished Welcome step, a Member to `/welcome/subscription-required`, and a suspended Account
to `/welcome/suspended` (`selling-mode.md`).

## Sub-features

- Email and password form (`/login`).
- Show/hide password toggle (`@login-password-toggle`).
- Link to reset the password (`/forgot-password`). The sign-up link (`@login-sign-up-link`) shows in selling mode
  even while the `new_signups` switch is off; that link then opens `/register`'s "Signups are closed for now" page.
- Google and GitHub sign-in (`/auth/google/redirect`, `/auth/github/redirect`): default off
  (`GOOGLE_AUTH_ENABLED` / `GITHUB_AUTH_ENABLED`). The buttons are absent when both flags are false; external OAuth is
  not drivable here.
- "Remember me", the guest language picker, an `?email=` prefill, and an invite link (`?invite=`) that takes the
  signed-in user to the invite.
- After sign-in the app redirects to `app.calendar` (`/schedule/calendar`), which renders the Week view; it never
  reads the remembered view. `redirect()->intended()` lands on a previously guarded page, and an invite lands on the
  invite page.

## How to get to it (user POV)

Open `http://localhost:8000/` signed out: the app sends guests to `/login`. Type the email and password, press
"Log in".

## Driving it with Playwright

- Journey: `$V/drive.sh sign-in`.
- Selectors: `@login-email`, `@login-password`, `@login-submit`.
- End state: the URL path starts with `/schedule` (`/schedule/calendar` for the fixture Owner), and
  `window.history.state.page.props.auth.user.email` is the fixture email. The screenshot shows the sidebar with
  "Verify Agent" / "Verify Desk" and the channel "Verify Desk".
- Side effect: none worth checking (a session row). The journey proves the core email/password path only; toggle,
  forgot/reset, OAuth, remember-me, locale and invite/intended redirects need separate proof when touched.

## Gotchas

- A new user sees the "Switch views" tour over the view switch. The journey leaves it on purpose (it is what a new
  user sees). Other journeys call `quietFirstVisits` and reload.
- The landing path is `/schedule/calendar` in a fresh context; check for `/schedule`, not one exact path, because
  `redirect()->intended()` can land elsewhere. The page's `view` prop is `week` for a new user, not "Calendar".
