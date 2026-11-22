---- MODULE BillingFacts ----
\* The facts of ticket #56 outside the trial race, as the code derives them:
\*   Account::hasPendingFirstCharge() / hasAppAccess() (Account.php:315,328)
\*   StripeInvoiceListener::handle() records first_paid_at only for a paid
\*     invoice above zero (StripeInvoiceListener.php:42-69); a subscription
\*     created active records nothing itself (StripeEventListener:78-93).
\*   Subscription::recordCancellation() / clearCancellation() re-read the row
\*     under a lock and leave a CancellationType::Admin row alone
\*     (Subscription.php:51-113), so a webhook that loaded the row earlier
\*     cannot replace an admin write.
EXTENDS Naturals, TLC

Statuses == {"none", "trialing", "active", "past_due", "canceled"}
Writers == {"w1", "w2"}
Types == {"none", "voluntary", "payment_failed", "admin"}
Amounts == {0, 1900}
Outcomes == {"cancel", "resume"}

VARIABLES status, firstPaid, positiveInvoice, zeroCoupon,
          subType, readDone, readVal, wrote, wasAdmin

vars == <<status, firstPaid, positiveInvoice, zeroCoupon,
          subType, readDone, readVal, wrote, wasAdmin>>

Init ==
  /\ status = "none"
  /\ firstPaid = FALSE
  /\ positiveInvoice = FALSE
  /\ zeroCoupon = FALSE
  /\ subType = "none"
  /\ readDone = [w \in Writers |-> FALSE]
  /\ readVal = [w \in Writers |-> "none"]
  /\ wrote = [w \in Writers |-> FALSE]
  /\ wasAdmin = FALSE

\* Checkout created a trialing subscription.
StartTrial ==
  /\ status = "none"
  /\ status' = "trialing"
  /\ UNCHANGED <<firstPaid, positiveInvoice, zeroCoupon, subType, readDone, readVal, wrote, wasAdmin>>

\* A checkout with a 100% coupon and no trial: Stripe creates the
\* subscription active and its first invoice is $0. The created webhook
\* records no payment; the $0 invoice proves none either.
SubscriptionBornActiveZeroCoupon ==
  /\ status = "none"
  /\ status' = "active"
  /\ zeroCoupon' = TRUE
  /\ UNCHANGED <<firstPaid, positiveInvoice, subType, readDone, readVal, wrote, wasAdmin>>

\* The trial's end: Stripe sends `active` before it attempts the charge.
TrialEndsActive ==
  /\ status = "trialing"
  /\ status' = "active"
  /\ UNCHANGED <<firstPaid, positiveInvoice, zeroCoupon, subType, readDone, readVal, wrote, wasAdmin>>

\* The first charge fails: the subscription turns past_due.
ChargeFails ==
  /\ status = "active"
  /\ status' = "past_due"
  /\ UNCHANGED <<firstPaid, positiveInvoice, zeroCoupon, subType, readDone, readVal, wrote, wasAdmin>>

\* invoice.payment_succeeded: only a paid invoice above zero proves payment.
InvoicePaid(amount) ==
  /\ amount \in Amounts
  /\ firstPaid' = IF amount > 0 THEN TRUE ELSE firstPaid
  /\ positiveInvoice' = IF amount > 0 THEN TRUE ELSE positiveInvoice
  /\ UNCHANGED <<status, zeroCoupon, subType, readDone, readVal, wrote, wasAdmin>>

\* A webhook handler loads the subscription row (subscriptionFor()).
WebhookRead(w) ==
  /\ readDone[w] = FALSE
  /\ readDone' = [readDone EXCEPT ![w] = TRUE]
  /\ readVal' = [readVal EXCEPT ![w] = subType]
  /\ UNCHANGED <<status, firstPaid, positiveInvoice, zeroCoupon, subType, wrote, wasAdmin>>

\* ... and later writes its outcome. The write re-reads the row under a lock
\* and decides from the stored value, not from the value it loaded earlier.
WebhookWrite(w, outcome) ==
  /\ readDone[w] = TRUE
  /\ wrote[w] = FALSE
  /\ outcome \in Outcomes
  /\ wrote' = [wrote EXCEPT ![w] = TRUE]
  /\ subType' = IF subType = "admin"
                THEN subType
                ELSE IF outcome = "cancel" THEN "voluntary" ELSE "none"
  /\ UNCHANGED <<status, firstPaid, positiveInvoice, zeroCoupon, readDone, readVal, wasAdmin>>

\* The System admin ends the subscription (admin app).
AdminCancel ==
  /\ subType # "admin"
  /\ subType' = "admin"
  /\ wasAdmin' = TRUE
  /\ UNCHANGED <<status, firstPaid, positiveInvoice, zeroCoupon, readDone, readVal, wrote>>

\* Stripe deletes the subscription after the retries run out.
StripeDeletes ==
  /\ status \in {"active", "past_due"}
  /\ status' = "canceled"
  /\ UNCHANGED <<firstPaid, positiveInvoice, zeroCoupon, subType, readDone, readVal, wrote, wasAdmin>>

Done ==
  /\ status \in {"canceled", "past_due"}
  /\ \A w \in Writers : wrote[w] = TRUE
  /\ UNCHANGED vars

Next ==
  \/ StartTrial
  \/ SubscriptionBornActiveZeroCoupon
  \/ TrialEndsActive
  \/ ChargeFails
  \/ \E amount \in Amounts : InvoicePaid(amount)
  \/ \E w \in Writers : WebhookRead(w)
  \/ \E w \in Writers, outcome \in Outcomes : WebhookWrite(w, outcome)
  \/ AdminCancel
  \/ StripeDeletes
  \/ Done

\* Account::hasPendingFirstCharge() blocks the access that
\* Cashier::keepPastDueSubscriptionsActive() would otherwise give.
HasAppAccess ==
  /\ status \in {"active", "trialing", "past_due"}
  /\ ~(status = "past_due" /\ firstPaid = FALSE)

\* Rules, in the reader's words.
NeverPaidNoAccess == (status = "past_due" /\ firstPaid = FALSE) => HasAppAccess = FALSE
PaidKeepsAccess == (status = "past_due" /\ firstPaid = TRUE) => HasAppAccess = TRUE
ZeroInvoiceIsNoProof == (positiveInvoice = FALSE) => (firstPaid = FALSE)
ZeroCouponIsNoProof == (zeroCoupon = TRUE /\ positiveInvoice = FALSE) => (firstPaid = FALSE)
AdminSticky == wasAdmin = TRUE => subType = "admin"

Spec == Init /\ [][Next]_vars

====
