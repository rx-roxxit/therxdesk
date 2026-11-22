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
   retried**; **an Account that paid before keeps access while past due.**
7. **A repeat card's Trial is ended at once** even when the trial-end call
   fails. Harm: the card that should have been refused keeps its Trial.

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

## What it finds at the sync commit (514a6e04)

Five checks fail. Each is a real execution of the current code, not a modelling
artefact; the `selftest-*` checks show the same rules pass on the fixed model.

### 1. Two Checkout Sessions give one Account two Trials (`account-one-trial`)

Account `a1` opens Checkout A with card `c1`. `ConfigureSubscriptionCheckout::trialDays()`
(`app/Support/Billing/ConfigureSubscriptionCheckout.php:57`) reads
`Account::canStartTrial()` (`app/Models/Account.php:219`) and it is true, so A
carries a Trial. Before A's webhook runs, `a1` opens Checkout B with card `c2`;
`canStartTrial()` is still true, because nothing local records a Trial until a
webhook writes `trial_started_at` / `trial_ends_at`. Both sessions complete. The
two `customer.subscription.created` handlers each claim a different card in
`trial_cards`, and each keeps its Trial. The Account now has two.

The model's trace: `OpenCheckout(s1) > OpenCheckout(s2) > CompleteCheckout(s1) >
CompleteCheckout(s2) > HandleCreatedTrialNewCard(s1) > HandleCreatedTrialNewCard(s2)`.

No two workers are needed: only two sessions created before the first webhook.
There is no account-level reservation to make `canStartTrial()` false while a
checkout is open.

### 2. A failed trial-end call leaves two Trials on one card (`card-repeat-one-trial`)

Accounts `a1` and `a2` check out with the same card `c1`. Worker 1 handles
`a1`'s `created`: `TrialCards::claim()` (`app/Support/Billing/TrialCards.php:16`)
inserts the fingerprint, so `a1` keeps its Trial. Worker 2 handles `a2`'s
`created`: the claim returns false, so `recordTrialStart()`
(`app/Listeners/StripeEventListener.php:197`) writes `trial_skipped_at` and
`trial_ended_at`, then `endRepeatCardTrial()` (`StripeEventListener.php:229`)
calls `subscriptions.update(trial_end: now)`. That call fails. The catch in
`endRepeatCardTrial` reverts the two timestamps and rethrows; `handle()` catches
and logs it (`StripeEventListener.php:63`), so Cashier answers 2xx and Stripe
never retries. `a2`'s Stripe trial keeps running: card `c1` has two Trials.

The model's trace: `OpenCheckout(s1) > OpenCheckout(s3) > CompleteCheckout(s1) >
CompleteCheckout(s3) > HandleCreatedTrialNewCard(s1) >
HandleCreatedTrialRepeatCard(s3) > EndTrialFails(s3)`.

### 3. A replayed created event ends its own Trial (`redelivered-created-self-skips`)

`a1`/`c1`'s `created` is handled; the claim row is `c1 -> s1` and
`trial_started_at` is set. The event-id key `stripe_webhook_event:<id>` lives in
the cache for one day (`StripeEventListener.php:337`), while Stripe retries a
failed delivery for up to three days. When the key is gone and the same event
arrives again, `alreadyProcessed()` returns false, `recordTrialStart()` runs
again, `TrialCards::claim()` correctly returns false — the row is its own — and
the code calls `endRepeatCardTrial()` on the subscription that owns the row,
setting `trial_end: now`. The legitimate Trial is cut short.

The model's trace: `OpenCheckout(s1) > CompleteCheckout(s1) >
HandleCreatedTrialNewCard(s1) > RedeliverCreated(s1)`.

The dedupe is a best-effort cache, not a durable idempotency key. This does not
create a second Trial; it kills the first one.

### 4. A concurrent admin cancellation is overwritten (`admin-cancellation-race`)

A webhook handler loads the subscription in `subscriptionFor()`
(`StripeEventListener.php:150`) with `cancellation_type = null`. Before
`recordCancellationFacts()` saves, the System admin records
`CancellationType::Admin`. The webhook then calls `recordCancellation()`
(`app/Models/Subscription.php:51`); it checks the in-memory `null`, not the
stored `Admin`, and writes `voluntary`. The admin type is lost.

The model's trace: `WebhookRead(w1) [readVal = none] > AdminCancel [subType = admin]
> WebhookWrite(w1) [subType = voluntary]`.

Sequential delivery is correct and the existing test covers it. Only the gap
between the row load and the save loses the admin type. This repo has no admin
writer (ADR 0004 puts it in the admin app); the model abstracts it as
`AdminCancel`. If that writer changes the row through a conditional update
instead of a load-and-save, re-model it.

### 5. A $0 first invoice counts as payment (`zero-coupon-first-payment`)

A Checkout with a 100% coupon and no Trial creates the subscription `active`,
and its first invoice is $0. Cashier sends `customer.subscription.created` with
status `active`; `handleSubscriptionCreated()` calls `recordFirstPayment()`
(`StripeEventListener.php:388`), which checks only `isNowActive()` and
`first_paid_at`, so it writes `first_paid_at = now()` with no money paid. The
invoice listener's `amount_paid > 0` guard (`StripeInvoiceListener.php:46`) never
runs on this path, and would not overwrite the value anyway.

The model's trace is one step: `SubscriptionBornActiveZeroCoupon`.

Consequence: `hasPaidSubscription()` is true, and `hasPendingFirstCharge()` is
false, so a later failed charge leaves the Account with access while past due —
the opposite of "only a paid invoice above zero proves the first payment". The
Trial path is correct: a Trial's end no longer records `first_paid_at`, and a $0
invoice leaves it null.

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

Sync commit: **514a6e04**. Confirmed against these files and functions:

| Code | Model action / variable |
| --- | --- |
| `ConfigureSubscriptionCheckout::trialDays()` / `apply()` — the Trial decision is frozen in the Stripe session | `OpenCheckout` |
| Stripe creating the subscription after the session completes | `CompleteCheckout` |
| `StripeEventListener::handleSubscriptionCreated()` with `trialing` | `HandleCreatedTrialNewCard` / `HandleCreatedTrialRepeatCard` |
| `TrialCards::claim()` — `insertOrIgnore` on the fingerprint primary key | the `claim` function, assigned in one action |
| `endRepeatCardTrial()` — success / failure and revert | `EndTrialOk` / `EndTrialFails`; `EndTrialRetries` in the fixed model |
| `recordFirstPayment()` on a subscription born `active` | `HandleCreatedActive`; `SubscriptionBornActiveZeroCoupon` |
| `StripeInvoiceListener::handle()` — `status = paid` and `amount_paid > 0` | `InvoicePaid(amount)` |
| `Subscription::recordCancellation()` — the `Admin` guard | `AdminSticky`, `WebhookRead` / `WebhookWrite` |
| `Account::canStartTrial()`, `hasHadTrial()`, `hasPendingFirstCharge()`, `hasAppAccess()` | `CanStartTrial`, `HasAppAccess` |
| `2026_11_22_120000_add_billing_facts.php` — `trial_cards.fingerprint` primary key | the atomic `claim` |

Covered files: `app/Listeners/StripeEventListener.php`,
`app/Listeners/StripeInvoiceListener.php`,
`app/Support/Billing/ConfigureSubscriptionCheckout.php`,
`app/Support/Billing/TrialCards.php`,
`app/Support/Billing/CardFingerprint.php`, `app/Models/Account.php`,
`app/Models/Subscription.php`,
`database/migrations/2026_11_22_120000_add_billing_facts.php`.

## Bounds and assumptions

- At most **2 accounts, 2 cards, 3 subscription slots**, one Trial length, one
  fingerprint per slot. TLC explores every order of the actions under that bound,
  not every payload variation.
- `insertOrIgnore` on a primary key is atomic (PostgreSQL and MySQL both). This
  is why the card claim itself has no interleaving.
- `Cache::add` is atomic; its keys can expire, which is the redelivery action.
- `CardFingerprint::forSubscription()` returns the card. A blank fingerprint
  returns before the claim (`StripeEventListener.php:210-214`), so that Trial is
  never claimed; this is not modelled.
- The trial-end API call has two outcomes: it succeeds, or it fails and the
  handler swallows the exception.
- The admin writer exists and writes the cancellation type directly, as an
  external app through the internal API.
- A 100% coupon with no Trial yields a subscription created `active` with a $0
  first invoice.
- Access is read from one subscription, as `Account::subscription()` does; the
  multi-subscription "latest row" selection is not modelled.

Not covered: fingerprint retrieval failures, Offers' `trial_days`, generic
Trials, plan and Offer writes, PostHog, dunning schedules, deletion/re-signup,
tax, currency and price logic.

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
