# Feature map

What a user can do in The RX Desk, and how to prove each one in the running app. One file per feature. A proof
that drives one convenient entry point is incomplete when the feature's file lists others.

| Feature | File | Journey (`drive.sh <name>`) |
| --- | --- | --- |
| Sign in with email and password | [sign-in.md](sign-in.md) | `sign-in` |
| Register a new Owner at `/register` (`selling.sh up`), and the EU/UK block | [registration.md](registration.md) | `register`, `blocked-country` |
| Write a post and save it as a draft | [compose-draft.md](compose-draft.md) | `compose-draft` |
| Storage bar in the account menu | [storage-bar.md](storage-bar.md) | `storage-bar` |
| AI credits: the low line, the Owner/Member refusal and the monthly override (`selling.sh up`, `fixture.sh up`, `ai-credits.sh none`) | [ai-credits.md](ai-credits.md) | `ai-credits` |
| Support chat: the Help menu, one real answer, the limit copy, the hand-off email (`selling.sh up`, `complimentary.sh up`, `support-chat.sh up\|limit`) | [support-chat.md](support-chat.md) | `support-chat`, `support-chat-limit`, `support-handoff` |
| Switch the app language from the sidebar | [language-switch.md](language-switch.md) | `language-switch` |
| Selling mode holds an unpaid Owner at Welcome (`selling.sh up`) | [selling-mode.md](selling-mode.md) | `selling-mode` |
| Welcome steps, Plan and Stripe Checkout (`selling.sh up`) | [welcome-checkout.md](welcome-checkout.md) | `welcome-checkout`, `welcome-plan` |
| Admin Offers on the Plan step (`selling.sh up`, `offer.sh up`) | [welcome-checkout.md](welcome-checkout.md) | `welcome-offer` |
| Billing: current plan, change plan, cards, Customer Portal (`selling.sh up`) | [billing.md](billing.md) | `billing` |
| Complimentary Plan: given, shown on the plan step and billing, then ended (`selling.sh up`, `complimentary.sh up`) | [complimentary.md](complimentary.md) | `complimentary`, `complimentary-ended` |
| Suspension and the publishing gate (`selling.sh up`, `suspension.sh up`) | [suspension.md](suspension.md) | `suspension`, `gate-schedule`, `gate-retry` |
| Availability: network and service switches, refusal on web/REST/MCP and retry, closed signups (`selling.sh up`, `fixture.sh up`, `complimentary.sh up`) | [availability.md](availability.md) | `availability-off`, `availability-retry`, `services-off` |
| Reviewer accounts and Review access (`selling.sh up`, `complimentary.sh up`, `review-access.sh on`) | [review-access.md](review-access.md) | `review-access` |
| Lifecycle emails, the 30-day deletion clock and the export/delete CLI (`selling.sh up`, `fixture.sh lifecycle`) | [lifecycle-deletion.md](lifecycle-deletion.md) | `lapsed-deletion` |
| Operator: one current legal entity, its history, the CLI and the internal API (`selling.sh up`) | [operator.md](operator.md) | no dedicated journey; `welcome-checkout` covers the Thai purchase document |
| Trial and payment: repeat card, failed first charge, past due, a lapse (`selling.sh up`, a Stripe test clock) | [trial-and-payment.md](trial-and-payment.md) | `welcome-checkout`, `payment-retry`, `past-due`, `lapsed` |
| Media on R2: uploads, previews, share-card avatars (`selling.sh up`, `media.sh up`) | [media-on-r2.md](media-on-r2.md) | `media-on-r2` |
| Internal API (`/internal/v1`) | [internal-api.md](internal-api.md) | not a browser journey; internal.sh |
| The dev email inbox (Mailpit) | [mailpit.md](mailpit.md) | not a browser journey; mail.sh |
| Admin alerts, heartbeat, capacity check and health (`selling.sh up`) | [admin-alerts.md](admin-alerts.md) | not a browser journey; selling.sh + internal.sh |
| Database backups, the backup heartbeat and a restore drill (`selling.sh up`) | [database-backups.md](database-backups.md) | not a browser journey; selling.sh + internal.sh + a scratch Postgres |

Not mapped yet: connecting a real channel (needs network OAuth, not possible with the fixture), Insights, Create (ideas,
templates, feeds), Repurpose, workspace members and invites, settings pages, the video policy and Cover, Offers
(banners, coupons, trials), usage limits per Plan. Add a file when you first verify one.
