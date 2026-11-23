# AI credits

Each Account has a monthly AI credit balance. The writing assistant, alt text, idea generation and the repurpose
shortener spend it; the AI tools show a quiet line when the balance is low, and refuse with a way forward when it is
gone.

## Sub-features

- **The gate.** At least 1 credit must be left before a call. The debit happens after the response, so a balance can
  reach zero on the last call.
- **The low-credits line** under the AI tools below 10% of the allowance (`@ai-credits-low`): "Only :remaining of
  :total AI credits left this month."
- **Out of credits, Owner**: the refusal names the renewal date and carries a **Change plan** link to Billing
  (`@writing-assistant-change-plan`).
- **Out of credits, Member**: the same title, and a body that points them to the workspace owner, with no link.
- **The ledger** (`ai_credit_usages`) is one row per call, per Account: feature, model, tokens, credits, User and
  Workspace. A Member spends the Owner's credits.
- **`/internal/v1`**: `PATCH /accounts/{uuid}/ai-credits` sets the monthly override (null clears it) and
  `POST .../ai-credits/reset` zeroes this month's use, both audited (`account.ai-credits.override` /
  `.reset`).
- **Our failure**: a provider error spends nothing and raises an URGENT admin alert; the customer sees "Couldn't
  reach AI right now. Try again in a few minutes." The provider and model never appear in a response.

## How to get to it (user POV)

`$V/selling.sh up`, `$V/fixture.sh up`, `$V/ai-credits.sh none` (a Complimentary Plan for access, a 120-credit
override and this month's usage at zero). Sign in as the fixture Owner, open **Create**, then **New post**, and open
**AI Assistant** in the composer.

## Driving it with Playwright

- Journey: `$V/drive.sh ai-credits` (selling mode).
- Selectors: `@composer-ai-assistant`, `@writing-assistant-panel`, `@ai-credits-low`,
  `@writing-assistant-mode-generate`, `@writing-assistant-prompt`, `@writing-assistant-generate`,
  `@writing-assistant-error`, `@writing-assistant-change-plan`.
- End state: the low line reads "Only 0 of 120 AI credits left this month."; pressing Generate shows "You've used
  this month's AI credits. They renew on :date." and a **Change plan** link; no JavaScript errors.
- Side effects: none from the journey. `ai-credits.sh low` leaves 5 of 120 (the line, no refusal);
  `ai-credits.sh up` restores a full balance; `ai-credits.sh clear` removes the seeded rows, the override and the
  Complimentary Plan.

## Gotchas

- Without an AI provider key, the journey stops at the gate: no model call, no ledger row. With a key configured
  (`AI_TEXT_PROVIDER=anthropic`, `ANTHROPIC_API_KEY`, `ANTHROPIC_TEXT_MODEL`), one real call writes a ledger row and
  moves the balance by `ceil((prompt + completion) / tokens_per_credit)`.
- The ledger rows are seeded by `ai-credits.sh` because the running app cannot make a metered call without a
  provider key; the override and reset themselves always go through `/internal/v1`.
- The month is the calendar month in UTC for everyone; a Trial gets the Catalog's fixed `trial_ai_credits` for its
  whole length instead. The renewal date is the next month's first day, or the Trial's end.
- The balance comes from the shared `aiCredits` prop and is refreshed by each AI response, so the line moves without
  a page visit.
