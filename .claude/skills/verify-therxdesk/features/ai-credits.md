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
  `.reset`). `GET /ai/settings` and `PATCH /ai/settings` own the token rate, the Trial's credits and the two model
  slots (`ai-settings.update`); `GET /ai/models` and `POST /ai/models/test` drive the slot pickers; `GET /ai/balances`
  and `GET /ai/ledger` read every Account's balance and the ledger. The full shapes live in `internal-api.md`.
- **The two model slots.** `catalog_settings.text_model` and `image_model` hold the System admin's pick, else the
  Config default (`rxdesk.ai.default_text_model` / `_image_model`). The free-text registry in
  `config/rxdesk.php` (`rxdesk.ai.models`) is the only source of a price and of image input; a model it does not
  price is never selectable. A changed slot is proved with one short real call before it saves.
- **The worst-case cost table** (`App\Services\Ai\AiCostTable`) prices every active Plan's whole monthly allowance at
  the text model's output rate, against the 10% of its discounted monthly price. It is computed live (no cache), so
  a Plan credit change, token rate, text model or running general Offer moves it at once. A change that pushes a
  Plan past the line stops with `requires_confirmation`.
- **The slot applies at call time.** `AiSettings::apply()` writes the effective model into the provider's text
  default right before each prompt (writing assistant, alt text, idea generation, the repurpose shortener), so a
  pick takes effect on the next call. Self-hosted keeps the environment's model and never overrides it.
- **Our failure**: a provider error spends nothing and raises an URGENT admin alert naming the applied model; the
  customer sees "Couldn't reach AI right now. Try again in a few minutes." The provider and model never appear in a
  customer response. A failed model *probe* is different: no credit, no ledger row, no alert, and the admin gets
  the error.

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
  moves the balance by `ceil((prompt + completion) / tokens_per_credit)`. In selling mode a saved slot overrides
  `ANTHROPIC_TEXT_MODEL` for the feature it serves (alt text uses the image slot).
- The ledger rows are seeded by `ai-credits.sh` because the running app cannot make a metered call without a
  provider key; the override and reset themselves always go through `/internal/v1`.
- The month is the calendar month in UTC for everyone; a Trial gets the Catalog's fixed `trial_ai_credits` for its
  whole length instead. The renewal date is the next month's first day, or the Trial's end.
- The balance comes from the shared `aiCredits` prop and is refreshed by each AI response, so the line moves without
  a page visit.
