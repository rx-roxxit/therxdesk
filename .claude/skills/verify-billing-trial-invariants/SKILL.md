---
name: verify-billing-trial-invariants
description: Model-check the Trial and payment rules of the Stripe webhook with TLA+ in Docker — one Trial per card and per Account, repeat deliveries, access during a retried first charge, a $0 first invoice, and admin cancellations. Use when changing app/Listeners/Stripe*.php, app/Support/Billing/*, the billing columns, or the access checks in app/Models/Account.php.
---

# Verify the billing trial invariants

A test checks the cases someone thought to write. This checks every interleaving
of the steps that grant a Trial and record a payment, under duplicate and
reordered webhooks and two workers at once. The code is modelled in TLA+ and TLC
explores every order; everything runs in Docker.

## The rules, in plain words

1. **One card starts at most one Trial, ever.** Harm if broken: a repeat card
   gets free days the pricing never sold.
2. **One Account starts at most one Trial, ever.** Harm: the same owner takes
   the Trial twice with two cards.
3. **A repeat delivery of a subscription's created event never ends that
   subscription's own Trial.** Harm: a legitimate first Trial is cut short.
4. **A cancellation the System admin recorded is never replaced.** Harm: the
   business record of who ended a subscription becomes wrong.
5. **A $0 first invoice is not proof that the Account paid.** Harm: an Account
   that never paid keeps app access while its next charge is retried.
6. **An Account that never paid has no access while its first charge is
   retried**; **an Account that paid before keeps access while past due,** unless
   a blocked checkout ended it (rule 8).
7. **A repeat card's Trial is ended at once** even when the trial-end call
   fails. Harm: the card that should have been refused keeps its Trial.
8. **A checkout billed to a blocked country leaves the Account without access,**
   even when the local cancel did not land. Harm: the service is used where it
   is not sold.

## Run it

```sh
.claude/skills/verify-billing-trial-invariants/run.sh
```

The first run builds `therxdesk-tla:1.7.4` (Java + TLC 2.19, pinned by image
digest and jar checksum; it needs the network once). Later runs take seconds.
Pass an id substring to run one check, e.g. `run.sh account`.

Outputs land in `.claude/skills/verify-billing-trial-invariants/last-run/`
(git-ignored): `summary.txt` and one `.out` per check with TLC's counterexample.

**Read every result with the pstack `/statechart` skill** (in Claude Code,
`/pstack:statechart`). It turns TLC's counterexample into the shortest path in
plain words, and a pass into a chart of the states. The `.out` file is TLC's raw
output; do not read a long state dump by eye when `/statechart` can draw it.

## Read the outcomes

| Outcome | Meaning | What to do |
| --- | --- | --- |
| `PASS` | The rule holds in every explored interleaving. | Nothing. |
| `KNOWN` | A reported finding in `known-findings.txt` still fails. | Read its trace; it is a finding, not an allowance. |
| `NEW` | A rule fails that the baseline does not list. | Report it with the trace. Do not weaken the rule. |
| `FIXED` | A known finding now passes. | Confirm the code fix, then remove its line from `known-findings.txt` and update this file. |
| `ERROR` | TLC did not finish (parse, type, state bound). | Read the `.out`; fix the model, not the rule. |
| drift | A covered file changed after `sync-commit`. | Re-read it, update the model, then move `sync-commit`. A pass with drift is not trusted. |

The exit code is non-zero for `NEW`, `ERROR`, `FIXED` and drift.

## What it covers at the sync commit

All checks pass. The five findings of 514a6e04 are fixed; each fix is the
mechanism the model now describes.

### 1. One Trial per Account (`account-one-trial`)

`StartSubscriptionCheckout::redirect()` expires the Account's other open
Checkout Sessions (`OpenCheckoutSessions::expire()`) before creating a new one,
so a second tab cannot normally complete. Two requests that list before either
creates a session can still both complete, so the webhook also claims the
Account's Trial: `TrialAccounts::claim()` (`trial_accounts.account_id`,
`insertOrIgnore`) makes the second subscription's claim `Taken`, and
`endRepeatCardTrial()` ends its Trial at once. The model's `OpenCheckout` may or
may not expire the other session (`expireOthers`), so TLC still explores the
race and the Account claim is what proves the rule.

### 2. A repeat card's Trial ends even when the call fails (`card-repeat-one-trial`)

`endRepeatCardTrial()` writes the skip facts, then calls
`subscriptions.update(trial_end: now)`. A failure no longer reverts them: it
raises `TrialEndFailed`, `handle()` gives the event id back to the cache and
rethrows, and the non-2xx answer makes Stripe redeliver the event. The redelivery
claims nothing new (`TrialClaim::Owned` / `Taken`) and calls the update again, so
the Trial ends on the retry. The model's `EndTrialFails` is a stutter: the skip
facts stay and `EndTrialOk` remains enabled.

### 3. A redelivery never ends its own Trial (`redelivered-created-self-skips`)

Both claims are idempotent per subscription. `TrialCards::claim()` and
`TrialAccounts::claim()` return `Owned` when the stored
`stripe_subscription_id` is the subscription asking, and `recordTrialStart()`
treats `Owned` as a winner. Only `Taken` calls `endRepeatCardTrial()`. The
event-id cache is still best-effort; a delivery after it expires is now safe.

### 4. An admin cancellation is sticky (`admin-cancellation-race`)

`Subscription::recordCancellation()` and `clearCancellation()` re-read the row
with `lockForUpdate()` inside a transaction and leave a
`CancellationType::Admin` row alone, whatever the handler loaded earlier. The
model's `WebhookWrite` decides from the stored `subType`, not the value read
before `AdminCancel`, and models both a cancellation and a resume.

### 5. A $0 invoice proves no payment (`zero-coupon-first-payment`)

`handleSubscriptionCreated()` no longer records `first_paid_at`: a subscription
born `active` (a 100% coupon, for one) is not proof of money.
`StripeInvoiceListener` writes it only for an invoice `status = paid` with
`amount_paid > 0`, and it is the only writer. The model's
`SubscriptionBornActiveZeroCoupon` leaves `firstPaid` false; `InvoicePaid(0)`
leaves it false and `InvoicePaid(1900)` sets it.

### 6. A blocked checkout has no access (`blocked-checkout-no-access`)

`StripeCheckoutSessionListener` writes `accounts.blocked_country_at` before it
calls Stripe to cancel the subscription, and `Account::hasAppAccess()` answers
false as soon as that fact is set. `BlockedCheckoutCancel` cancels the status;
`BlockedCheckoutCancelFails` keeps the status and leaves only the fact, and the
invariant still refuses access.

### Not modelled: two subscriptions can coexist

Nothing stops a second Stripe subscription from being created while the first
is still `active` (two completions in the race above, or a server-side gate that
does not exist yet). The model bounds one subscription row per slot and reads
access from one subscription, as `Account::subscription()` does; the double
billing that follows is reported separately (#67) and not covered here.

## A bug, or a modelling error?

1. Open the code line in the coverage table below and read it. The model treats
   one action as one indivisible step: one webhook handler step, or one
   atomic statement. If the code splits or joins those steps, the model is wrong.
2. Replay the trace in the code's words: every action must be a call the code
   really makes, in that order, with that precondition.
3. Run the matching `selftest-*` check. It passes on `TrialGrantFixed.tla`,
   which closes the gaps counterfactually. If the real model fails and the fixed
   one passes, the check is live; the difference between the two models names
   the missing step.
4. If the code matches the model, it is a finding. Report it and leave the rule
   alone. Never weaken an invariant or a precondition to make the run green.

## When the code changes

`run.sh` fails on drift and lists every covered file changed since
`sync-commit` (committed or not). Then:

1. Re-read the changed file and decide whether the model still describes it.
2. Update `specs/*.tla` and the coverage table here in the same change.
3. Run the checks; the `selftest-*` ones must still pass.
4. Only then write the new commit into `sync-commit`.
5. A fixed known finding: remove its line from `known-findings.txt`, update the
   findings here, and keep the invariant.
6. A new failure: add it to this file and report it; add it to
   `known-findings.txt` only if the owner accepts it as the current baseline.

## Coverage record

Sync commit: **cee5136d**. Confirmed against these files and functions:

| Code | Model action / variable |
| --- | --- |
| `ConfigureSubscriptionCheckout::trialDays()` / `apply()` — the Trial decision is frozen in the Stripe session; an Offer that does not cover the Plan and interval gives no trial | `OpenCheckout` |
| `StartSubscriptionCheckout::redirect()` — an uncovered or leftover Offer is dropped before the session, so the model's `CanStartTrial` is a superset of the Trials a Checkout can start | `OpenCheckout` |
| `OpenCheckoutSessions::expire()` — the Account's other sessions are expired, or the race misses them | `OpenCheckout`'s `expireOthers` |
| Stripe creating the subscription after the session completes | `CompleteCheckout` |
| `StripeEventListener::handleSubscriptionCreated()` with `trialing` | `HandleCreatedTrialNewCard` / `HandleCreatedTrialRepeatCard` / `HandleCreatedTrialAccountTaken` |
| `TrialAccounts::claim()` — `insertOrIgnore` on the `trial_accounts` Account id | the `acctClaim` function |
| `TrialCards::claim()` — `insertOrIgnore` on the fingerprint; `Owned` for this subscription's own row | the `claim` function; `RedeliverCreated` |
| `endRepeatCardTrial()` — `TrialEndFailed` on a failed call, non-2xx and a redelivery | `EndTrialOk` / `EndTrialFails`; `EndTrialRetries` in the fixed model |
| `handleSubscriptionCreated()` on a subscription born `active` — no first payment | `HandleCreatedActive`; `SubscriptionBornActiveZeroCoupon` |
| `StripeInvoiceListener::handle()` — `status = paid` and `amount_paid > 0` | `InvoicePaid(amount)` |
| `StripeCheckoutSessionListener::handle()` — records `accounts.blocked_country*`, then cancels the subscription | `BlockedCheckoutCancel` / `BlockedCheckoutCancelFails` |
| `Subscription::recordCancellation()` / `clearCancellation()` — re-read under a lock, `Admin` kept | `AdminSticky`, `WebhookRead` / `WebhookWrite` |
| `Account::canStartTrial()`, `hasHadTrial()`, `hasPendingFirstCharge()` | `CanStartTrial`, `HasAppAccess` |
| `Account::hasAppAccess()` — a blocked checkout ends access, a paid past-due keeps it | `HasAppAccess`, `BlockedNoAccess` |
| `2026_11_22_120000_add_billing_facts.php` — `trial_cards.fingerprint` primary key | the atomic card `claim` |
| `2026_11_22_130000_add_trial_accounts.php` — `trial_accounts.account_id` primary key | the atomic Account `claim` |
| `2026_11_22_140000_add_country_to_users_and_blocked_country_to_accounts.php` — `users.country`, `accounts.blocked_country*` | the facts `BlockedCheckoutCancel` writes |

Covered files: `app/Listeners/StripeEventListener.php`,
`app/Listeners/StripeInvoiceListener.php`,
`app/Actions/Billing/StartSubscriptionCheckout.php`,
`app/Exceptions/Billing/TrialEndFailed.php`,
`app/Support/Billing/ConfigureSubscriptionCheckout.php`,
`app/Support/Billing/OpenCheckoutSessions.php`,
`app/Support/Billing/TrialAccounts.php`,
`app/Support/Billing/TrialCards.php`,
`app/Support/Billing/CardFingerprint.php`,
`app/Support/Billing/BlockedCountries.php`,
`app/Listeners/StripeCheckoutSessionListener.php`, `app/Models/Account.php`,
`app/Models/Subscription.php`, `app/Models/TrialAccount.php`,
`database/migrations/2026_11_22_120000_add_billing_facts.php`,
`database/migrations/2026_11_22_130000_add_trial_accounts.php`,
`database/migrations/2026_11_22_140000_add_country_to_users_and_blocked_country_to_accounts.php`.

## Bounds and assumptions

- At most **2 accounts, 2 cards, 3 subscription slots**, one Trial length, one
  fingerprint per slot, one Account claim per account. TLC explores every order
  of the actions under that bound, not every payload variation.
- `insertOrIgnore` on a primary key is atomic (PostgreSQL and MySQL both). This
  is why neither claim has an interleaving.
- `Cache::add` is atomic; its keys can expire, which is the redelivery action.
  A handler that answers non-2xx gives the key back, which the model treats as
  the `EndTrialFails` retry.
- `CardFingerprint::forSubscription()` returns the card. A blank fingerprint was
  never claimed before this change either; the Account claim still runs first,
  and the model assumes a fingerprint is present.
- The trial-end API call has two outcomes: it succeeds, or it fails, keeps the
  skip facts and is retried by Stripe's redelivery.
- The admin writer exists and writes the cancellation type directly, as an
  external app through the internal API (assumed to block on the row lock, as a
  plain `UPDATE` does).
- A 100% coupon with no Trial yields a subscription created `active` with a $0
  first invoice.
- Access is read from one subscription, as `Account::subscription()` does; the
  multi-subscription "latest row" selection is not modelled. Two subscriptions
  can coexist, which is reported separately, not checked here.

No-card Trials are gone (November 2026): `StartGenericTrial`,
`Offer::startsWithoutCard()`, `requires_card` and the listener's
`subscribedDuringTrialWithoutCard` conversion were removed, so every Trial now
starts at Stripe Checkout and is covered by `CanStartTrial`/`OpenCheckout`. A
legacy generic trial (`accounts.trial_ends_at`) still grants access, which
`Account::hasAppAccess()` models.

Not covered: fingerprint retrieval failures, an Offer's `trial_days` and
coverage beyond the gate above, plan and Offer writes, PostHog, dunning
schedules, deletion/re-signup, tax, currency and price logic.

## Add a check

1. Add the invariant to `specs/TrialGrant.tla` or `specs/BillingFacts.tla`. It
   must be a rule a reader agrees to, in words, not a restatement of the code.
2. Add `specs/MC_<id>.cfg` with `SPECIFICATION Spec` and the invariant.
3. Add an `add_check` line in `run.sh`.
4. Run it. If it fails on the current code, decide with the owner whether it is
   a finding to report or the accepted baseline; only the latter goes into
   `known-findings.txt`.
5. Keep `TrialGrantFixed.tla` in step if the new rule is about a gap the fixed
   model closes.
