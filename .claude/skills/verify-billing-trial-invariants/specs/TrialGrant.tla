---- MODULE TrialGrant ----
\* Model of the trial rules of ticket #56, as the code implements them:
\*   ConfigureSubscriptionCheckout::trialDays() decides at Checkout-Session
\*   creation, from Account::canStartTrial() (app/Models/Account.php:219).
\*   StripeEventListener::recordTrialStart() then claims the card through
\*   TrialCards::claim() (insertOrIgnore on trial_cards.fingerprint) and
\*   endRepeatCardTrial() ends a repeat card's trial through the Stripe API.
\* Two workers handle webhooks concurrently; Stripe can also fail the trial-end
\* call, which the listener reverts and swallows (StripeEventListener:229-251).
\*
\* Accounts {a1,a2}, cards {c1,c2}, subscriptions:
\*   s1 = a1/c1, s2 = a1/c2 (the same Account, two cards),
\*   s3 = a2/c1 (the same card, two Accounts).
EXTENDS Naturals, FiniteSets, TLC

Accounts == {"a1", "a2"}
Cards == {"c1", "c2"}
SUBS == {"s1", "s2", "s3"}
AcctOf == [s1 |-> "a1", s2 |-> "a1", s3 |-> "a2"]
CardOf == [s1 |-> "c1", s2 |-> "c2", s3 |-> "c1"]

CONSTANT AllowRedelivery

VARIABLES open, sessionTrial, exists, stripeTrialing,
          started, skipped, ending, handled, replayed, ever, claim, firstPaid

vars == <<open, sessionTrial, exists, stripeTrialing, started, skipped,
          ending, handled, replayed, ever, claim, firstPaid>>

Init ==
  /\ open = [s \in SUBS |-> FALSE]
  /\ sessionTrial = [s \in SUBS |-> FALSE]
  /\ exists = [s \in SUBS |-> FALSE]
  /\ stripeTrialing = [s \in SUBS |-> FALSE]
  /\ started = [s \in SUBS |-> FALSE]
  /\ skipped = [s \in SUBS |-> FALSE]
  /\ ending = [s \in SUBS |-> FALSE]
  /\ handled = [s \in SUBS |-> FALSE]
  /\ replayed = [s \in SUBS |-> FALSE]
  /\ ever = [s \in SUBS |-> FALSE]
  /\ claim = [f \in Cards |-> "none"]
  /\ firstPaid = [a \in Accounts |-> FALSE]

\* Account::canStartTrial() at the moment the Checkout Session is created.
CanStartTrial(a) ==
  /\ firstPaid[a] = FALSE
  /\ \A s \in SUBS : (AcctOf[s] = a) => (started[s] = FALSE)

\* ConfigureSubscriptionCheckout::apply(): the session is created with or
\* without a trial. That decision is frozen in the Stripe session.
OpenCheckout(s) ==
  /\ open[s] = FALSE
  /\ exists[s] = FALSE
  /\ open' = [open EXCEPT ![s] = TRUE]
  /\ sessionTrial' = [sessionTrial EXCEPT ![s] = CanStartTrial(AcctOf[s])]
  /\ UNCHANGED <<exists, stripeTrialing, started, skipped, ending, handled,
                 replayed, ever, claim, firstPaid>>

\* Checkout completes: Stripe creates the subscription (trialing or active).
\* No local subscription row exists yet; the webhook has not run.
CompleteCheckout(s) ==
  /\ open[s] = TRUE
  /\ exists[s] = FALSE
  /\ exists' = [exists EXCEPT ![s] = TRUE]
  /\ stripeTrialing' = IF sessionTrial[s]
                         THEN [stripeTrialing EXCEPT ![s] = TRUE]
                         ELSE stripeTrialing
  /\ firstPaid' = IF sessionTrial[s]
                    THEN firstPaid
                    ELSE [firstPaid EXCEPT ![AcctOf[s]] = TRUE]
  /\ UNCHANGED <<open, sessionTrial, started, skipped, ending, handled,
                 replayed, ever, claim>>

\* customer.subscription.created, trialing, first claim of the card.
HandleCreatedTrialNewCard(s) ==
  /\ exists[s] = TRUE
  /\ handled[s] = FALSE
  /\ sessionTrial[s] = TRUE
  /\ claim[CardOf[s]] = "none"
  /\ handled' = [handled EXCEPT ![s] = TRUE]
  /\ started' = [started EXCEPT ![s] = TRUE]
  /\ claim' = [claim EXCEPT ![CardOf[s]] = s]
  /\ ever' = [ever EXCEPT ![s] = TRUE]
  /\ UNCHANGED <<open, sessionTrial, exists, stripeTrialing, skipped, ending,
                 replayed, firstPaid>>

\* customer.subscription.created, trialing, card already claimed: the skip
\* facts are written before the Stripe call.
HandleCreatedTrialRepeatCard(s) ==
  /\ exists[s] = TRUE
  /\ handled[s] = FALSE
  /\ sessionTrial[s] = TRUE
  /\ claim[CardOf[s]] # "none"
  /\ handled' = [handled EXCEPT ![s] = TRUE]
  /\ started' = [started EXCEPT ![s] = TRUE]
  /\ skipped' = [skipped EXCEPT ![s] = TRUE]
  /\ ending' = [ending EXCEPT ![s] = TRUE]
  /\ UNCHANGED <<open, sessionTrial, exists, stripeTrialing, replayed, ever,
                 claim, firstPaid>>

\* customer.subscription.created, active: recordFirstPayment().
HandleCreatedActive(s) ==
  /\ exists[s] = TRUE
  /\ handled[s] = FALSE
  /\ sessionTrial[s] = FALSE
  /\ handled' = [handled EXCEPT ![s] = TRUE]
  /\ firstPaid' = [firstPaid EXCEPT ![AcctOf[s]] = TRUE]
  /\ UNCHANGED <<open, sessionTrial, exists, stripeTrialing, started, skipped,
                 ending, replayed, ever, claim>>

\* subscriptions.update(trial_end: now) succeeded.
EndTrialOk(s) ==
  /\ ending[s] = TRUE
  /\ ending' = [ending EXCEPT ![s] = FALSE]
  /\ stripeTrialing' = [stripeTrialing EXCEPT ![s] = FALSE]
  /\ UNCHANGED <<open, sessionTrial, exists, started, skipped, handled,
                 replayed, ever, claim, firstPaid>>

\* subscriptions.update(trial_end: now) failed: skip facts reverted, the
\* exception logged and swallowed, so the Stripe trial keeps running.
EndTrialFails(s) ==
  /\ ending[s] = TRUE
  /\ ending' = [ending EXCEPT ![s] = FALSE]
  /\ skipped' = [skipped EXCEPT ![s] = FALSE]
  /\ ever' = [ever EXCEPT ![s] = TRUE]
  /\ UNCHANGED <<open, sessionTrial, exists, stripeTrialing, started, handled,
                 replayed, claim, firstPaid>>

\* The same subscription.created handled again after the event-id cache key
\* is gone (Cache::add expires after a day; Stripe retries up to three days).
RedeliverCreated(s) ==
  /\ AllowRedelivery = TRUE
  /\ exists[s] = TRUE
  /\ sessionTrial[s] = TRUE
  /\ started[s] = TRUE
  /\ stripeTrialing[s] = TRUE
  /\ skipped[s] = FALSE
  /\ ending[s] = FALSE
  /\ replayed[s] = FALSE
  /\ replayed' = [replayed EXCEPT ![s] = TRUE]
  /\ skipped' = [skipped EXCEPT ![s] = TRUE]
  /\ ending' = [ending EXCEPT ![s] = TRUE]
  /\ UNCHANGED <<open, sessionTrial, exists, stripeTrialing, started, handled,
                 ever, claim, firstPaid>>

AllDone ==
  \A s \in SUBS : handled[s] = TRUE /\ ending[s] = FALSE

Done == AllDone /\ UNCHANGED vars

Next ==
  \/ \E s \in SUBS : OpenCheckout(s)
  \/ \E s \in SUBS : CompleteCheckout(s)
  \/ \E s \in SUBS : HandleCreatedTrialNewCard(s)
  \/ \E s \in SUBS : HandleCreatedTrialRepeatCard(s)
  \/ \E s \in SUBS : HandleCreatedActive(s)
  \/ \E s \in SUBS : EndTrialOk(s)
  \/ \E s \in SUBS : EndTrialFails(s)
  \/ \E s \in SUBS : RedeliverCreated(s)
  \/ Done

\* A trial that was effectively kept: the webhook saw it, Stripe still runs
\* it, and no skip facts ended it. `ever` is the history of that state.
TrialKept(s) ==
  /\ started[s] = TRUE
  /\ stripeTrialing[s] = TRUE
  /\ skipped[s] = FALSE

\* Rules, in the reader's words.
CardTrialsKept(f) == {s \in SUBS : ever[s] = TRUE /\ CardOf[s] = f}
AccountTrialsKept(a) == {s \in SUBS : ever[s] = TRUE /\ AcctOf[s] = a}
CardOneTrial == \A f \in Cards : Cardinality(CardTrialsKept(f)) <= 1
AccountOneTrial == \A a \in Accounts : Cardinality(AccountTrialsKept(a)) <= 1
WinnerNotSkipped == \A s \in SUBS : (claim[CardOf[s]] = s) => skipped[s] = FALSE

Spec == Init /\ [][Next]_vars

====
