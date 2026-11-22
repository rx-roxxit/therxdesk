# Feature map

What a user can do in The RX Desk, and how to prove each one in the running app. One file per feature. A proof
that drives one convenient entry point is incomplete when the feature's file lists others.

| Feature | File | Journey (`drive.sh <name>`) |
| --- | --- | --- |
| Sign in with email and password | [sign-in.md](sign-in.md) | `sign-in` |
| Register a new Owner at `/register` (`selling.sh up`) | [registration.md](registration.md) | `register` |
| Write a post and save it as a draft | [compose-draft.md](compose-draft.md) | `compose-draft` |
| Storage bar in the account menu | [storage-bar.md](storage-bar.md) | `storage-bar` |
| Switch the app language from the sidebar | [language-switch.md](language-switch.md) | `language-switch` |
| Selling mode holds an unpaid Owner at Welcome (`selling.sh up`) | [selling-mode.md](selling-mode.md) | `selling-mode` |
| Welcome steps, Plan and Stripe Checkout (`selling.sh up`) | [welcome-checkout.md](welcome-checkout.md) | `welcome-checkout` |
| Billing: current plan, change plan, cards, Customer Portal (`selling.sh up`) | [billing.md](billing.md) | `billing` |
| Media on R2: uploads, previews, share-card avatars (`selling.sh up`, `media.sh up`) | [media-on-r2.md](media-on-r2.md) | `media-on-r2` |
| Internal API (`/internal/v1`) | [internal-api.md](internal-api.md) | not a browser journey; internal.sh |
| The dev email inbox (Mailpit) | [mailpit.md](mailpit.md) | not a browser journey; mail.sh |
| Admin alerts, heartbeat, capacity check and health (`selling.sh up`) | [admin-alerts.md](admin-alerts.md) | not a browser journey; selling.sh + internal.sh |
| Database backups, the backup heartbeat and a restore drill (`selling.sh up`) | [database-backups.md](database-backups.md) | not a browser journey; selling.sh + internal.sh + a scratch Postgres |

Not mapped yet: connecting a real channel (needs network OAuth, not possible with the fixture), publishing (would
call the network), Insights, Create (ideas, templates, feeds), Repurpose, workspace members and invites, settings
pages, the video policy and Cover, Offers (banners, coupons, trials), usage limits per Plan, past-due and cancelled
subscriptions. Add a file when you first verify one.
