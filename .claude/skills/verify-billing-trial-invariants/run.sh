#!/usr/bin/env bash
# Run the TLA+ checks of the billing trial invariants.
# Usage: run.sh [id-substring ...]
# Everything runs in Docker; nothing is installed on the host.

set -uo pipefail

SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(git -C "$SKILL_DIR" rev-parse --show-toplevel)"
SPEC_DIR="$SKILL_DIR/specs"
OUT_DIR="${TLC_OUT_DIR:-$SKILL_DIR/last-run}"
IMAGE="therxdesk-tla:1.7.4"
SYNC_COMMIT="$(tr -d '[:space:]' < "$SKILL_DIR/sync-commit")"

mkdir -p "$OUT_DIR"
SUMMARY="$OUT_DIR/summary.txt"
: > "$SUMMARY"

# Files the model describes. A change here needs a model review first.
COVERED=(
  app/Listeners/StripeEventListener.php
  app/Listeners/StripeInvoiceListener.php
  app/Actions/Billing/StartSubscriptionCheckout.php
  app/Exceptions/Billing/TrialEndFailed.php
  app/Support/Billing/ConfigureSubscriptionCheckout.php
  app/Support/Billing/OpenCheckoutSessions.php
  app/Support/Billing/TrialAccounts.php
  app/Support/Billing/TrialCards.php
  app/Support/Billing/CardFingerprint.php
  app/Actions/Billing/ChangePlan.php
  app/Services/Billing/ComplimentaryPlans.php
  app/Models/Account.php
  app/Models/Subscription.php
  app/Models/TrialAccount.php
  app/Listeners/StripeCheckoutSessionListener.php
  app/Support/Billing/BlockedCountries.php
  database/migrations/2026_11_22_120000_add_billing_facts.php
  database/migrations/2026_11_22_130000_add_trial_accounts.php
  database/migrations/2026_11_22_140000_add_country_to_users_and_blocked_country_to_accounts.php
  database/migrations/2026_11_23_000000_add_complimentary_plan_to_accounts_table.php
  database/migrations/2026_11_23_020000_add_suspension_to_accounts_table.php
)

if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
  echo "Building $IMAGE (first run needs network)..." >&2
  docker build -t "$IMAGE" "$SKILL_DIR" >&2 || {
    echo "Could not build $IMAGE" >&2
    exit 2
  }
fi

# --- Drift: has the described code changed since the last confirmed sync? ---
CHANGED="$(git -C "$REPO_ROOT" diff --name-only "$SYNC_COMMIT" -- "${COVERED[@]}" 2>/dev/null || true)"
UNCOMMITTED="$(git -C "$REPO_ROOT" status --porcelain -- "${COVERED[@]}" 2>/dev/null || true)"
DRIFT=0

if [ -n "$CHANGED" ] || [ -n "$UNCOMMITTED" ]; then
  DRIFT=1
  {
    echo "DRIFT: code changed since sync commit ${SYNC_COMMIT:0:10}."
    echo "Re-read the files below, update specs/*.tla when the behavior changed,"
    echo "then put the new commit in sync-commit only after confirming the model."
    if [ -n "$CHANGED" ]; then
      echo "changed since the sync commit:"
      printf '  %s\n' $CHANGED
    fi
    if [ -n "$UNCOMMITTED" ]; then
      echo "uncommitted:"
      printf '  %s\n' $UNCOMMITTED
    fi
    echo
  } | tee -a "$SUMMARY"
fi

# --- Checks ---------------------------------------------------------------
# id | module | config | rule in the reader's words
declare -A MODULE CFG RULE
ORDER=()

add_check() {
  ORDER+=("$1")
  MODULE[$1]="$2"
  CFG[$1]="$3"
  RULE[$1]="$4"
}

add_check card-repeat-one-trial TrialGrant MC_card.cfg \
  "One card starts at most one Trial, ever."
add_check account-one-trial TrialGrant MC_account.cfg \
  "One Account starts at most one Trial, ever."
add_check redelivered-created-self-skips TrialGrant MC_redeliver.cfg \
  "A repeat delivery of a subscription's created event never ends that subscription's own Trial."
add_check admin-cancellation-race BillingFacts MC_AdminSticky.cfg \
  "A cancellation the System admin recorded is never replaced."
add_check zero-coupon-first-payment BillingFacts MC_ZeroCouponIsNoProof.cfg \
  "A \$0 first invoice is not proof that the Account paid."
add_check never-paid-no-access BillingFacts MC_NeverPaidNoAccess.cfg \
  "An Account that never paid has no access while its first charge is retried."
add_check paid-before-keeps-access BillingFacts MC_PaidKeepsAccess.cfg \
  "An Account that paid before keeps access while past due, unless it is suspended."
add_check blocked-checkout-no-access BillingFacts MC_BlockedNoAccess.cfg \
  "A checkout billed to a blocked country leaves the Account without access."
add_check complimentary-access BillingFacts MC_ComplimentaryAccess.cfg \
  "A Complimentary Plan in force grants access unless the Account is suspended; once it ends the Account has none and is Lapsed."
add_check complimentary-no-trial TrialGrant MC_ComplimentaryNoTrial.cfg \
  "An Account given a Complimentary Plan never starts a Trial."
add_check suspended-no-access BillingFacts MC_SuspendedNoAccess.cfg \
  "A suspended Account has no app access, whatever its billing state."
add_check selftest-card-fixed TrialGrantFixed FIX_CardOneTrial.cfg \
  "Self-test: the card rule passes on the fixed model."
add_check selftest-account-fixed TrialGrantFixed FIX_AccountOneTrial.cfg \
  "Self-test: the Account rule passes on the fixed model."
add_check selftest-redelivery-fixed TrialGrantFixed FIX_WinnerNotSkipped.cfg \
  "Self-test: a redelivery leaves the winner's Trial alone on the fixed model."

FILTER=("$@")
WANTED() {
  local id="$1"
  if [ "${#FILTER[@]}" -eq 0 ]; then
    return 0
  fi
  local pat
  for pat in "${FILTER[@]}"; do
    case "$id" in
      *"$pat"*) return 0 ;;
    esac
  done
  return 1
}

known() {
  grep -q "^$1|" "$SKILL_DIR/known-findings.txt" 2>/dev/null
}

trace_of() {
  grep -oE 'State [0-9]+: <[A-Za-z]+' "$1" \
    | sed -E 's/State [0-9]+: <//' \
    | paste -sd '>' - \
    | sed 's/>/ > /g'
}

FAILED=0
for id in "${ORDER[@]}"; do
  WANTED "$id" || continue

  out="$OUT_DIR/$id.out"
  docker run --rm -v "$SPEC_DIR":/spec:ro -w /spec "$IMAGE" \
    -workers 1 -metadir "/tmp/tlc-$id" -config "${CFG[$id]}" "${MODULE[$id]}" >"$out" 2>&1

  if grep -q 'No error has been found' "$out"; then
    if [[ "$id" == selftest-* ]]; then
      outcome=PASS
    elif known "$id"; then
      outcome=FIXED
      FAILED=1
    else
      outcome=PASS
    fi
  elif grep -q 'is violated' "$out"; then
    if known "$id"; then
      outcome=KNOWN
    else
      outcome=NEW
      FAILED=1
    fi
  else
    outcome=ERROR
    FAILED=1
  fi

  printf '%-7s %-36s %s\n' "$outcome" "$id" "${RULE[$id]}"
  printf '%-7s %-36s %s\n' "$outcome" "$id" "${RULE[$id]}" >> "$SUMMARY"

  if [ "$outcome" = KNOWN ] || [ "$outcome" = NEW ]; then
    tr="$(trace_of "$out")"
    echo "        trace: $tr"
    echo "        trace: $tr" >> "$SUMMARY"
  fi

  if [ "$outcome" = FIXED ]; then
    echo "        This known finding now passes. Confirm the code change, then remove its line from known-findings.txt."
    echo "        Update SKILL.md's finding list too." >> "$SUMMARY"
  fi

  if [ "$outcome" = ERROR ]; then
    echo "        TLC did not finish; read $out."
    echo "        TLC did not finish; read $out." >> "$SUMMARY"
  fi
done

echo
if [ "$DRIFT" -eq 1 ]; then
  echo "Result: code drift — the pass below does not describe the current code."
  echo "Result: code drift — the pass does not describe the current code." >> "$SUMMARY"
fi
if [ "$FAILED" -eq 1 ]; then
  echo "Result: a new failure, an error, or a fixed finding needs attention (see above)."
  echo "Result: a new failure, an error, or a fixed finding needs attention." >> "$SUMMARY"
elif [ "$DRIFT" -eq 1 ]; then
  echo "Result: drift only; reconcile the model, then move sync-commit."
else
  echo "Result: same as the baseline at the sync commit."
  echo "Result: same as the baseline at the sync commit." >> "$SUMMARY"
fi

echo "Outputs: $OUT_DIR"
[ "$DRIFT" -eq 1 ] && exit 1
[ "$FAILED" -eq 1 ] && exit 1
exit 0
