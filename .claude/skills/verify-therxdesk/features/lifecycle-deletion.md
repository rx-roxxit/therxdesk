# Lifecycle emails, the deletion clock and the data export

When access stops, the Account keeps a stored deletion clock. Emails warn the Owner (and, at seven days, every
Member), the Lapsed plan step shows the date, and the data is deleted 30 days later while the Owner, the empty
Account and the Stripe customer stay. A never-paid signup is removed entirely 30 days after signup, with no email.
The System admin can export an Owner's posts and delete an Account's data through CLI commands, and delete at once
through `/internal/v1` by typing the Owner's email.

## Customer-visible contract

- **The deletion clock is stored.** `accounts.deletion_scheduled_at` is written when access stops (never computed
  from the subscription later). A Suspended Account's clock is frozen; lifting the Suspension adds the frozen time
  back. `accounts.data_purged_at` marks an Account whose data was already deleted while the Owner was kept. In
  current code that marker is one-way: after the first retained-Owner purge, a later subscription and lapse does not
  create a second deletion clock or purge. Treat that as a product gap to report, not a skill behavior to change.
- **The emails** (Maizzle, keys in `rxdesk.lifecycle`, 16 locales): Trial ending three days before the charge (date,
  amount after the Offer, how to cancel in the billing portal); access stopped; deletion in seven days; deletion in
  one day; and the Member's own warning seven days before. Each deletion email says an export can be requested. Each
  one is recorded in `lifecycle_emails`, so it goes out once per recipient.
- **The Lapsed plan step** shows `Your subscription ended. Your data will be deleted on <date> unless you subscribe
  again.` (`@welcome-plan-lapsed-deletion`). A never-paid signup gets no such line.
- **Deletion at day 30** removes what `DeleteUser` removes for an Owner (workspaces, posts, R2 media, channels,
  Invites, Members, and Support chat transcripts once they exist) and keeps the Owner's User, the empty Account and
  the Stripe customer. The Owner can sign in and subscribe again with no Trial.
- **Never-paid deletion** removes the User and the Account too, and sends no email.
- **The CLI** (`rxdesk:export`, `rxdesk:delete`) both require `--account=<uuid>`, support `--dry-run`, and are off
  while the app is self-hosted. The export writes `posts.json` plus each media file into one zip.
- **`/internal/v1`** carries `access_state.deletion_scheduled_at` (and `never_paid`, `data_purged_at`) on Customer
  detail, and `POST /accounts/{uuid}/delete` with `{"confirm_email": "<Owner email>"}` deletes at once, audited as
  `account.delete_now`. A wrong email is a 422 and deletes nothing.

## How a user reaches it

The Owner's subscription ends (or a Complimentary Plan ends). The lifecycle sweep sets the clock and sends "access
stopped". The Owner is at `/welcome/plan`, which now names the date. Seven days before, the Owner and every Member
get their warning. One day before, the Owner gets the last one. At day 30 the sweep deletes the data and keeps the
Owner, Account and Stripe customer.

## How to drive it (host sequence)

Needs `selling.sh up` (the internal tokens and selling money paths) and a fixture.

```sh
$V/launch.sh
$V/selling.sh up
$V/fixture.sh up

# 1. Put the fixture on the Lapsed path with a deletion clock 7 days out.
#    fixture.sh up above is required: an already-subscribed fixture keeps its active
#    subscription and the next sweep can clear the manually set clock.
$V/fixture.sh lifecycle 168          # hours; 168 = 7 days, -1 = already due
$V/lifecycle.sh show                 # access_state.deletion_scheduled_at ~ now + 7 days

# 2. The plan step names the date.
$V/drive.sh lapsed-deletion

# 3. Send the 7-day Owner and Member emails through Mailpit.
$V/mail.sh clear
$V/lifecycle.sh sweep
$V/lifecycle.sh emails

# 4. Prove the read-only commands without touching data.
$V/lifecycle.sh export               # rxdesk:export --dry-run: posts/media counts
$V/lifecycle.sh delete-dry           # rxdesk:delete --dry-run: what would go

# 5. Delete the fixture's data for real, and only it.
$V/lifecycle.sh delete
$V/db.sh "select owner_id, stripe_id, data_purged_at, deletion_scheduled_at from accounts where id = '<accountId>'"
$V/db.sh "select count(*) from workspaces where account_id = '<accountId>'"
#   accounts: owner_id and stripe_id kept, data_purged_at set, deletion_scheduled_at null
#   workspaces: 0
# A fresh fixture has no Stripe customer. Attach a test-clock Customer first if the
# run must prove retention instead of observing null-to-null.
$V/db.sh "select email from users where id = '<userId>'"     # still there
$V/db.sh "select email from users where email = 'verify-member@example.com'"   # gone

$V/fixture.sh up                     # reset
$V/selling.sh down
```

Take `<accountId>` and `<userId>` (the Owner's id) from `$V/fixture.sh show`.

The never-paid path (User removed entirely, no email) is covered by
`tests/Feature/Lifecycle/DeletionTest.php` and
`tests/Feature/Lifecycle/LifecycleEmailsTest.php`; the live sequence above proves the customer path, where the
Owner is kept.

## Host-side proof

- `fixture.sh lifecycle 168` prints the Account id and the stored `deletionScheduledAt`.
- `lifecycle.sh show` prints `{"has_app_access": false, "lapsed": true, "never_paid": false, "deletion_scheduled_at":
  "...", "data_purged_at": null, ...}`.
- `lifecycle.sh sweep` runs `rxdesk:lifecycle --account=<id>` then flushes the queue; the Mailpit inbox then holds
  `Your data will be deleted in 7 days` to the Owner and `Your workspace will be deleted in 7 days` to the Member.
  Running `sweep` again sends neither a second time (`lifecycle_emails` has one row per recipient and type).
- `lifecycle.sh export` prints the dry-run counts and writes nothing; the non-dry `rxdesk:export --account=<id>`
  writes a zip whose `posts.json` lists the posts and whose `media/` entries hold the files. The stock fresh fixture
  has no posts and no media, so the counts are zero unless the run creates them first.
- `lifecycle.sh delete-dry` prints the impact and changes nothing; `lifecycle.sh delete` then leaves the Owner, the
  empty Account and the Stripe customer, and `audit_logs` is untouched by the CLI (only the internal "Delete now"
  writes `account.delete_now`).
- The screenshot `lapsed-deletion-plan` shows the plan step with the deletion date.

## Gotchas

- **Order matters.** `drive.sh lapsed-deletion` needs `fixture.sh lifecycle 168` first, or the plan step has no
  date and the journey fails.
- **Mailpit only sees mail after the queue is consumed.** Horizon normally does this; `lifecycle.sh sweep` also
  runs `queue:work --stop-when-empty` as a fallback. That flush is global: quiesce unrelated queued work and never
  run a second worker beside it.
- **The CLI deletes the fixture only.** `lifecycle.sh delete` passes `--account=<fixture>`; never run
  `rxdesk:delete` without it.
- **Suspension freezes the clock.** A suspended fixture gets no email and is not deleted; `lifecycle.sh backdate`
  clears a prior suspension.
- **Self-hosted turns all of it off.** `selling.sh up` is required; the scheduled sweep skips self-hosted, and both
  commands refuse.
