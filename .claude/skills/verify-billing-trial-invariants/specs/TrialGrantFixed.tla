---- MODULE TrialGrantFixed ----
\* Counterfactual variant used only to show that the checks in TrialGrant can
\* pass: a Trial is reserved for the Account atomically when the Checkout
\* Session is created, a repeat card's own redelivered event keeps its Trial,
\* and a failed trial-end call stays pending instead of restoring the Trial.
\* This is not the code. The real model is TrialGrant.tla.
EXTENDS Naturals, FiniteSets, TLC

Accounts == {"a1", "a2"}
Cards == {"c1", "c2"}
SUBS == {"s1", "s2", "s3"}
AcctOf == [s1 |-> "a1", s2 |-> "a1", s3 |-> "a2"]
CardOf == [s1 |-> "c1", s2 |-> "c2", s3 |-> "c1"]

CONSTANT AllowRedelivery

VARIABLES open, sessionTrial, exists, stripeTrialing,
          started, skipped, ending, handled, replayed, ever, claim, acctClaim, firstPaid

vars == <<open, sessionTrial, exists, stripeTrialing, started, skipped,
          ending, handled, replayed, ever, claim, acctClaim, firstPaid>>

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
  /\ acctClaim = [a \in Accounts |-> "none"]
  /\ firstPaid = [a \in Accounts |-> FALSE]

CanStartTrial(a) ==
  /\ firstPaid[a] = FALSE
  /\ acctClaim[a] = "none"
  /\ \A s \in SUBS : (AcctOf[s] = a) => (started[s] = FALSE)

OpenCheckout(s) ==
  /\ open[s] = FALSE
  /\ exists[s] = FALSE
  /\ open' = [open EXCEPT ![s] = TRUE]
  /\ sessionTrial' = [sessionTrial EXCEPT ![s] = CanStartTrial(AcctOf[s])]
  /\ acctClaim' = IF CanStartTrial(AcctOf[s])
                    THEN [acctClaim EXCEPT ![AcctOf[s]] = s]
                    ELSE acctClaim
  /\ UNCHANGED <<exists, stripeTrialing, started, skipped, ending, handled,
                 replayed, ever, claim, firstPaid>>

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
                 replayed, ever, claim, acctClaim>>

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
                 replayed, acctClaim, firstPaid>>

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
                 claim, acctClaim, firstPaid>>

HandleCreatedActive(s) ==
  /\ exists[s] = TRUE
  /\ handled[s] = FALSE
  /\ sessionTrial[s] = FALSE
  /\ handled' = [handled EXCEPT ![s] = TRUE]
  /\ firstPaid' = [firstPaid EXCEPT ![AcctOf[s]] = TRUE]
  /\ UNCHANGED <<open, sessionTrial, exists, stripeTrialing, started, skipped,
                 ending, replayed, ever, claim, acctClaim>>

EndTrialOk(s) ==
  /\ ending[s] = TRUE
  /\ ending' = [ending EXCEPT ![s] = FALSE]
  /\ stripeTrialing' = [stripeTrialing EXCEPT ![s] = FALSE]
  /\ UNCHANGED <<open, sessionTrial, exists, started, skipped, handled,
                 replayed, ever, claim, acctClaim, firstPaid>>

\* The call failed: the durable skip facts stay, the trial-end is retried.
EndTrialRetries(s) ==
  /\ ending[s] = TRUE
  /\ UNCHANGED vars

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
  /\ IF claim[CardOf[s]] = s
     THEN UNCHANGED <<skipped, ending>>
     ELSE /\ skipped' = [skipped EXCEPT ![s] = TRUE]
          /\ ending' = [ending EXCEPT ![s] = TRUE]
  /\ UNCHANGED <<open, sessionTrial, exists, stripeTrialing, started, handled,
                 ever, claim, acctClaim, firstPaid>>

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
  \/ \E s \in SUBS : EndTrialRetries(s)
  \/ \E s \in SUBS : RedeliverCreated(s)
  \/ Done

TrialKept(s) ==
  /\ started[s] = TRUE
  /\ stripeTrialing[s] = TRUE
  /\ skipped[s] = FALSE

CardTrialsKept(f) == {s \in SUBS : ever[s] = TRUE /\ CardOf[s] = f}
AccountTrialsKept(a) == {s \in SUBS : ever[s] = TRUE /\ AcctOf[s] = a}
CardOneTrial == \A f \in Cards : Cardinality(CardTrialsKept(f)) <= 1
AccountOneTrial == \A a \in Accounts : Cardinality(AccountTrialsKept(a)) <= 1
WinnerNotSkipped == \A s \in SUBS : (claim[CardOf[s]] = s) => skipped[s] = FALSE

Spec == Init /\ [][Next]_vars

====
