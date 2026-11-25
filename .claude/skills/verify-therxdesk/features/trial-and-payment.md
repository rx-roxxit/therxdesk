# Trial and payment paths

The four end-to-end paths of the Trial and access rules, proven on the dev stack against the Stripe sandbox with a
Stripe test clock. `tests/Feature/Billing/TrialAndPaymentWebhookTest.php` proves the handlers with Cashier payloads;
these paths prove Stripe sends them and the app answers.

The paths share one setup: a fresh fixture, a Stripe test clock, and a sandbox Customer on that clock, pointed at by
the fixture Account. A current Operator must already exist if the path asserts the Thai purchase document; neither
helper creates one. `stripe-clock.sh` moves the clock so a Trial can end, a renewal can fail and Smart Retries can
run out without waiting days. `fixture.sh up` deletes the fixture Account's Trial records (`trial_cards` and
`trial_accounts`), so `4242` can start a Trial again on every run.

## Setup (once per path)

```sh
$V/launch.sh
$V/selling.sh up
$V/fixture.sh up
$V/stripe-clock.sh delete            # no-op when there is none
$V/stripe-clock.sh create
$V/stripe-clock.sh customer          # verify-agent@example.com
$V/stripe-clock.sh attach            # fixture.sh customer <cus_...>
$V/stripe-clock.sh status            # clock id, status, frozen time, customer
```

`stripe-clock.sh create` refuses while a clock exists: `delete` it first, or let `cleanup.sh` do it. The clock does
not change the app; it only changes what Stripe does with that Customer's subscriptions.

Every path starts with `drive.sh welcome-checkout`, whose `payOnStripeCheckout()` picks Thailand as the billing
country and asserts it. After paying, the buyer waits on `/billing/processing` until this Checkout Session's facts
arrive, reads the Thai purchase document and presses Continue (`welcome-checkout.md`). The fixture Owner's
`users.country` is `TH` too. Keep it that way: the completed-checkout webhook cancels a subscription billed to an
EU/UK/EEA/Crown-dependency country, and such a cancellation would end every path below before its Trial even
starts.

The sandbox refuses raw card numbers sent to its API (`Sending credit card numbers directly to the Stripe API is
generally unsafe`), so every API command below uses Stripe's magic test PaymentMethods: `pm_card_visa` (the `4242`
card) and `pm_card_chargeCustomerFail` (`4000000000000341`, which attaches but fails when charged). On Stripe
Checkout's own card form the raw number is still fine, which is what `VERIFY_TEST_CARD` types.

## 1. A repeat card is charged at once

`fixture.sh card` claims a card fingerprint for the fixture Account, as if it had started a Trial before.
`fingerprint pm_card_visa` prints the fingerprint of the `4242` card that `welcome-checkout` types.

```sh
$V/fixture.sh card "$($V/stripe-clock.sh fingerprint pm_card_visa)"
$V/drive.sh welcome-checkout
$V/db.sh "select a.plan_id, a.first_paid_at, a.first_invoice_id, s.stripe_status, s.trial_started_at, s.trial_ended_at, s.trial_skipped_at, s.paid_without_trial_at from accounts a join subscriptions s on s.account_id = a.id where a.id = '<accountId>' order by s.created_at desc limit 1"
$V/db.sh "select fingerprint, account_id, stripe_subscription_id from trial_cards where account_id = '<accountId>'"
```

Expected: `stripe_status` `active`; `trial_started_at`, `trial_ended_at`, `trial_skipped_at` and
`paid_without_trial_at` all set; `first_paid_at` and `first_invoice_id` set; one `trial_cards` row with the `4242`
fingerprint and a null `stripe_subscription_id` (the seeded row wins: the repeat card was never claimed). The journey
still ends on the Schedule with `hasActiveSubscription` true and Mini.

Stripe's newer invoice payload carries `subscription: null` and puts the id under
`parent.subscription_details.subscription`. `StripeValue::invoiceSubscriptionId()` reads both, so `paid_without_trial_at`
must be set on this path too; a null there is a regression, not a known gap.

## 2. Never paid, the first charge fails

A first-ever card gets the Catalog Trial; the charge after it is declined, so the Account has no access until it pays.
`4000000000000341` saves on Checkout but fails when charged.

```sh
VERIFY_TEST_CARD=4000000000000341 $V/drive.sh welcome-checkout
$V/stripe-clock.sh advance "$(date -d '+8 days' +%s)"
$V/drive.sh payment-retry
$V/db.sh "select a.plan_id, a.first_paid_at, a.first_invoice_id, s.stripe_status, s.trial_started_at, s.trial_ended_at, s.trial_skipped_at from accounts a join subscriptions s on s.account_id = a.id where a.id = '<accountId>' order by s.created_at desc limit 1"
```

Expected after the advance: `stripe_status` `past_due`, `first_paid_at` and `first_invoice_id` null,
`trial_started_at` and `trial_ended_at` set, `trial_skipped_at` null. `payment-retry` lands on `/welcome/plan`, sees
"Your first payment didn't go through", no plan cards, and the Update card button opens `billing.stripe.com`.

Pay the open invoice with an attached `4242` PaymentMethod, then reload `/welcome/plan` (it opens the Schedule).
The test-clock Customer cannot pay the magic `pm_card_visa` directly; attach it first and use the returned `pm_...`:

```sh
CUS=<cus_...>
INV=$($V/stripe.sh get /v1/invoices -d status=open -d customer="$CUS" | python3 -I -c 'import json,sys; print(json.load(sys.stdin)["data"][0]["id"])')
PM=$($V/stripe.sh post /v1/payment_methods/pm_card_visa/attach -d customer="$CUS" | python3 -I -c 'import json,sys; print(json.load(sys.stdin)["id"])')
$V/stripe.sh post "/v1/invoices/$INV/pay" -d payment_method="$PM"
```

The `invoice.payment_succeeded` webhook now sets `first_invoice_id` and `first_paid_at`, and the subscription turns
active: the same query shows both set, and `/welcome/plan` redirects into the app.

## 3. Paid before, the renewal fails

```sh
$V/drive.sh welcome-checkout
$V/stripe-clock.sh advance "$(date -d '+8 days' +%s)"
$V/db.sh "select a.plan_id, a.first_paid_at, a.first_invoice_id, s.stripe_status from accounts a join subscriptions s on s.account_id = a.id where a.id = '<accountId>' order by s.created_at desc limit 1"
$V/db.sh "select stripe_id from subscriptions where account_id = '<accountId>' order by created_at desc limit 1"   # the sub_... id
$V/stripe.sh post /v1/payment_methods/pm_card_chargeCustomerFail/attach -d customer=<cus_...>   # note the pm_... id
$V/stripe.sh post /v1/subscriptions/<sub_...> -d default_payment_method=<pm_...>
# The clock is already ~8 days ahead of the host: advance from its frozen time.
F=$($V/stripe-clock.sh status | awk '/frozen_time/{print $2}')
$V/stripe-clock.sh advance "$((F + 32*86400))"
$V/drive.sh past-due
$V/db.sh "select a.plan_id, a.first_paid_at, a.first_invoice_id, s.stripe_status from accounts a join subscriptions s on s.account_id = a.id where a.id = '<accountId>' order by s.created_at desc limit 1"
```

The subscription's own default payment method is what the renewal charges; setting it is what makes the renewal fail.
The update takes the attached `pm_...` id, not the `pm_card_chargeCustomerFail` alias. (The customer default,
`-d invoice_settings[default_payment_method]=...`, is only the third fallback and is optional here.)

Expected: the first charge paid, so `first_paid_at` and `first_invoice_id` are set and `stripe_status` is `active`.
After the renewal advance, `stripe_status` is `past_due` and both paid facts stay. `past-due` proves access is kept:
the Schedule opens, the sidebar shows `@sidebar-past-due`, and billing shows the Plan with the `Past due` badge.

## 4. Smart Retries run out (a lapse)

Continuing from path 3, the declining card fails every retry and the sandbox cancels the subscription.

```sh
# Step from the clock's frozen time in 4-day increments until the subscription cancels.
F=$($V/stripe-clock.sh status | awk '/frozen_time/{print $2}')
for d in 4 8 12 16 20 24 28; do
  $V/stripe-clock.sh advance "$((F + d*86400))"
  STATUS=$($V/db.sh "select s.stripe_status from subscriptions s where s.account_id = '<accountId>' order by s.created_at desc limit 1" | awk 'NR==4 {print $1}')
  echo "retry step $d: $STATUS"
  [ "$STATUS" = "canceled" ] && break
done
$V/drive.sh lapsed
$V/db.sh "select a.plan_id, a.first_paid_at, s.stripe_status, s.cancellation_type, s.cancelled_at, s.offer_id from accounts a join subscriptions s on s.account_id = a.id where a.id = '<accountId>' order by s.created_at desc limit 1"
```

Expected: `stripe_status` `canceled`, `cancellation_type` `payment_failed`, `cancelled_at` set, `plan_id` null,
`offer_id` null. A claimed Offer's redemption stays in `account_offer.redeemed_at`, so it cannot be used again.
`lapsed` proves the Account is back at `/welcome/plan` with no retry state, `@plan-select-mini` reading "Subscribe"
(no second Trial) and no Offer banner.

The sandbox cancelled 12 days after the failed renewal in the November 23, 2026 run, so a single large jump is not
enough: `advance` takes an absolute epoch, and a host-side `date -d '+120 days'` can land before the clock's own
frozen time (the clock a previous path moved is already ahead of the host). Read the frozen time from
`stripe-clock.sh status` and advance in 4-day steps, checking the subscription's `stripe_status` after each, until
it is `canceled`.

## Gotchas

- The clock stays where the last path left it: `payment-retry`, `past-due` and `lapsed` need the state their path
  built, not a fresh fixture. Run the path's setup, not `fixture.sh up`, in between.
- `advance` is absolute, so compute each target from the clock's frozen time, not the host clock: the host date
  falls behind once a path has moved the clock, and `date -d '+N days'` then jumps backwards. Step the retries
  (4 days at a time) and read the subscription between them.
- A journey answers "needs selling mode": run `selling.sh up`.
- A repeat card is charged when you expected a Trial: `fixture.sh up` clears the fixture's `trial_cards` and
  `trial_accounts`; a run without it keeps the previous claim.
- `advance` does not reach `ready`: `stripe-clock.sh status` shows the clock; Stripe leaves it `pending` while an
  event is delivered. Run `advance` again with the same target.
- A run leaves the clock and its Customer: `cleanup.sh` deletes the Customer through `fixture.sh down` and the clock
  through `stripe-clock.sh delete`.
