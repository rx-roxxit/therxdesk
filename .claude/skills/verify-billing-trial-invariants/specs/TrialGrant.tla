---- MODULE TrialGrant ----
\* Model of the trial rules of ticket #56, as the code implements them:
\*   ConfigureSubscriptionCheckout::trialDays() decides at Checkout-Session
\*   creation, from Account::canStartTrial() (app/Models/Account.php:219).
\*   StartSubscriptionCheckout::redirect() expires the Account's other open
\*   Checkout Sessions first (OpenCheckoutSessions::expire()), so a second tab
\*   normally cannot complete; two requests that list before either creates
\*   can still both complete, which the webhook then guards.
\*   StripeEventListener::recordTrialStart() claims the Account's Trial
\*   (TrialAccounts::claim(), trial_accounts.account_id) and the card
\*   (TrialCards::claim(), trial_cards.fingerprint), both insertOrIgnore.
\*   endRepeatCardTrial() ends a repeat card's Trial through the Stripe API.
\*   A failed call keeps the skip facts and raises TrialEndFailed: the webhook
\*   answers non-2xx and Stripe redelivers the event, so the call is retried.
\* A redelivery of the same created event finds both claims written by its own
\* subscription and is a no-op (TrialClaim::Owned).
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

VARIABLES open, expired, sessionTrial, exists, stripeTrialing,
          started, skipped, ending, handled, replayed, ever, claim, acctClaim, firstPaid

vars == <<open, expired, sessionTrial, exists, stripeTrialing, started, skipped,
          ending, handled, replayed, ever, claim, acctClaim, firstPaid>>

Init ==
  /\ open = [s \in SUBS |-> FALSE]
  /\ expired = [s \in SUBS |-> FALSE]
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
  /\ acctClaim = [a \in Accounts |-> "none"]
  /\ firstPaid = [a \in Accounts |-> FALSE]

\* Account::canStartTrial() at the moment the Checkout Session is created.
CanStartTrial(a) ==
  /\ firstPaid[a] = FALSE
  /\ acctClaim[a] = "none"
  /\ \A s \in SUBS : (AcctOf[s] = a) => (started[s] = FALSE)

\* ConfigureSubscriptionCheckout::apply(): the session is created with or
\* without a trial. That decision is frozen in the Stripe session.
\* OpenCheckoutSessions::expire() closes the Account's other open sessions in
\* the same request; `expireOthers` is FALSE for the race where both requests
\* listed before either created its session.
OpenCheckout(s, expireOthers) ==
  /\ open[s] = FALSE
  /\ exists[s] = FALSE
  /\ expireOthers \in BOOLEAN
  /\ open' = [open EXCEPT ![s] = TRUE]
  /\ sessionTrial' = [sessionTrial EXCEPT ![s] = CanStartTrial(AcctOf[s])]
  /\ expired' = IF expireOthers
                THEN [x \in SUBS |-> expired[x] \/ (open[x] /\ AcctOf[x] = AcctOf[s])]
                ELSE expired
  /\ UNCHANGED <<exists, stripeTrialing, started, skipped, ending, handled,
                 replayed, ever, claim, acctClaim, firstPaid>>

\* Checkout completes: Stripe creates the subscription (trialing or active).
\* No local subscription row exists yet; the webhook has not run. An expired
\* session cannot complete.
CompleteCheckout(s) ==
  /\ open[s] = TRUE
  /\ expired[s] = FALSE
  /\ exists[s] = FALSE
  /\ exists' = [exists EXCEPT ![s] = TRUE]
  /\ stripeTrialing' = IF sessionTrial[s]
                         THEN [stripeTrialing EXCEPT ![s] = TRUE]
                         ELSE stripeTrialing
  /\ firstPaid' = IF sessionTrial[s]
                    THEN firstPaid
                    ELSE [firstPaid EXCEPT ![AcctOf[s]] = TRUE]
  /\ UNCHANGED <<open, expired, sessionTrial, started, skipped, ending, handled,
                 replayed, ever, claim, acctClaim>>

\* customer.subscription.created, trialing: both claims are free, so this
\* subscription takes the Account's one Trial and the card's.
HandleCreatedTrialNewCard(s) ==
  /\ exists[s] = TRUE
  /\ handled[s] = FALSE
  /\ sessionTrial[s] = TRUE
  /\ acctClaim[AcctOf[s]] \in {"none", s}
  /\ claim[CardOf[s]] = "none"
  /\ handled' = [handled EXCEPT ![s] = TRUE]
  /\ started' = [started EXCEPT ![s] = TRUE]
  /\ claim' = [claim EXCEPT ![CardOf[s]] = s]
  /\ acctClaim' = [acctClaim EXCEPT ![AcctOf[s]] = s]
  /\ ever' = [ever EXCEPT ![s] = TRUE]
  /\ UNCHANGED <<open, expired, sessionTrial, exists, stripeTrialing, skipped,
                 ending, replayed, firstPaid>>

\* customer.subscription.created, trialing, the Account's Trial is already
\* another subscription's: the skip facts are written before the Stripe call.
HandleCreatedTrialAccountTaken(s) ==
  /\ exists[s] = TRUE
  /\ handled[s] = FALSE
  /\ sessionTrial[s] = TRUE
  /\ acctClaim[AcctOf[s]] # "none"
  /\ acctClaim[AcctOf[s]] # s
  /\ handled' = [handled EXCEPT ![s] = TRUE]
  /\ started' = [started EXCEPT ![s] = TRUE]
  /\ skipped' = [skipped EXCEPT ![s] = TRUE]
  /\ ending' = [ending EXCEPT ![s] = TRUE]
  /\ UNCHANGED <<open, expired, sessionTrial, exists, stripeTrialing, replayed,
                 ever, claim, acctClaim, firstPaid>>

\* customer.subscription.created, trialing, the card already had a Trial: the
\* Account's claim is taken while it is free, the card is not claimed.
HandleCreatedTrialRepeatCard(s) ==
  /\ exists[s] = TRUE
  /\ handled[s] = FALSE
  /\ sessionTrial[s] = TRUE
  /\ acctClaim[AcctOf[s]] \in {"none", s}
  /\ claim[CardOf[s]] # "none"
  /\ claim[CardOf[s]] # s
  /\ handled' = [handled EXCEPT ![s] = TRUE]
  /\ started' = [started EXCEPT ![s] = TRUE]
  /\ skipped' = [skipped EXCEPT ![s] = TRUE]
  /\ ending' = [ending EXCEPT ![s] = TRUE]
  /\ acctClaim' = [acctClaim EXCEPT ![AcctOf[s]] = s]
  /\ UNCHANGED <<open, expired, sessionTrial, exists, stripeTrialing, replayed,
                 ever, claim, firstPaid>>

\* customer.subscription.created, active: a subscription born without a trial
\* records no payment by itself (only a paid invoice above zero proves one).
HandleCreatedActive(s) ==
  /\ exists[s] = TRUE
  /\ handled[s] = FALSE
  /\ sessionTrial[s] = FALSE
  /\ handled' = [handled EXCEPT ![s] = TRUE]
  /\ UNCHANGED <<open, expired, sessionTrial, exists, stripeTrialing, started,
                 skipped, ending, replayed, ever, claim, acctClaim, firstPaid>>

\* subscriptions.update(trial_end: now) succeeded.
EndTrialOk(s) ==
  /\ ending[s] = TRUE
  /\ ending' = [ending EXCEPT ![s] = FALSE]
  /\ stripeTrialing' = [stripeTrialing EXCEPT ![s] = FALSE]
  /\ UNCHANGED <<open, expired, sessionTrial, exists, started, skipped, handled,
                 replayed, ever, claim, acctClaim, firstPaid>>

\* subscriptions.update(trial_end: now) failed: the skip facts stay and the
\* handler answers non-2xx, so Stripe redelivers the event and the call runs
\* again. Modelled as a stutter: the Trial is not restored.
EndTrialFails(s) ==
  /\ ending[s] = TRUE
  /\ UNCHANGED vars

\* The same subscription.created handled again after the event-id cache key
\* is gone (Cache::add expires after a day; Stripe retries up to three days).
\* Its own claims are TrialClaim::Owned, so its Trial is left alone; any other
\* conflict still ends it.
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
  /\ IF acctClaim[AcctOf[s]] = s /\ claim[CardOf[s]] = s
     THEN UNCHANGED <<skipped, ending, acctClaim>>
     ELSE /\ skipped' = [skipped EXCEPT ![s] = TRUE]
          /\ ending' = [ending EXCEPT ![s] = TRUE]
          /\ acctClaim' = IF acctClaim[AcctOf[s]] = "none"
                          THEN [acctClaim EXCEPT ![AcctOf[s]] = s]
                          ELSE acctClaim
  /\ UNCHANGED <<open, expired, sessionTrial, exists, stripeTrialing, started,
                 handled, ever, claim, firstPaid>>

\* An expired session never completes; a user who still wants that Plan
\* starts a new one. Everything else has been handled and its Stripe call is
\* not still pending.
AllDone ==
  \A s \in SUBS : (handled[s] \/ expired[s]) /\ ending[s] = FALSE

Done == AllDone /\ UNCHANGED vars

Next ==
  \/ \E s \in SUBS, expireOthers \in BOOLEAN : OpenCheckout(s, expireOthers)
  \/ \E s \in SUBS : CompleteCheckout(s)
  \/ \E s \in SUBS : HandleCreatedTrialNewCard(s)
  \/ \E s \in SUBS : HandleCreatedTrialAccountTaken(s)
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
