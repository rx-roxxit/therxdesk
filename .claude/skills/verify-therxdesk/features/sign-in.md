# Sign in

A user with an email and password signs in and lands on the Schedule of their current Workspace.

## Sub-features

- Email and password form (`/login`).
- Show/hide password toggle (`@login-password-toggle`).
- Link to sign up (`@login-sign-up-link`) and to reset the password (`/forgot-password`).
- Google and GitHub sign-in (`/auth/google/redirect`, `/auth/github/redirect`): external OAuth, not drivable here.
- After sign-in, the Schedule opens on the view the user picked last (Calendar for a new user).

## How to get to it (user POV)

Open `http://localhost:8000/` signed out: the app sends guests to `/login`. Type the email and password, press
"Log in".

## Driving it with Playwright

- Journey: `$V/drive.sh sign-in`.
- Selectors: `@login-email`, `@login-password`, `@login-submit`.
- End state: the URL path starts with `/schedule` (`/schedule/calendar` for the fixture Owner), and
  `window.history.state.page.props.auth.user.email` is the fixture email. The screenshot shows the sidebar with
  "Verify Agent" / "Verify Desk" and the channel "Verify Desk".
- Side effect: none worth checking (a session row).

## Gotchas

- A new user sees the "Switch views" tour over the view switch. The journey leaves it on purpose (it is what a new
  user sees). Other journeys call `quietFirstVisits` and reload.
- The landing path depends on the remembered view, so check for `/schedule`, not one exact path.
