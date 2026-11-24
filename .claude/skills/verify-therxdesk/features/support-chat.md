# Support chat

An AI assistant in the Help menu that answers from the app's own help pages and the customer's account facts. It
spends no AI credits, so it keeps working when the balance is gone. See ADR 0007.

## Sub-features

- **Where:** Help menu → **Chat support** opens the sheet (a right sheet on desktop, full screen on mobile). The
  Help menu also offers **Email support**. Both exist only in selling mode; the chat also needs the AI provider
  installed and Availability's `service:support_chat` row switched on.
- **The help corpus** is `resources/help/*.md`: English, ten topics plus a glossary. The whole corpus goes into
  every prompt, and a test fails above about 40k tokens.
- **The brief** is built on the server each turn. For the Owner it carries Plan, status, Trial end, AI credits and
  Storage; a Member's brief has none of the Account block, only the workspace count, channels with state and last
  error, up to five failed posts, the pending-approval count and the page the chat was opened from. No email, name
  or avatar, and the agent has no tools.
- **Limits:** 20 user questions per conversation, 40 per user per day (00:00 UTC), 2,000 characters a question, 10
  a minute. Assistant replies do not spend the conversation or daily allowance. A refusal returns `code` =
  `conversation`, `daily` or `minute`. A `conversation` or `daily` refusal locks the input with the matching line; a
  `minute` refusal shows its own line but leaves the input enabled. The `daily` line carries the reset time in the
  user's time zone.
- **The ledger** gets one row per call with feature `support_chat` and `credits` 0; the balance does not move.
- **A provider failure** shows "Can't answer right now." with **Try again**, stores the question, spends nothing
  and raises the URGENT `ai_provider_failure` alert naming the model.
- **Storage:** conversations and messages (with 👍/👎) live in `support_conversations` and `support_messages`. The
  last conversation reopens within 24 hours; **New conversation** starts another. A daily command prunes
  conversations after 30 days of inactivity (creation time is the fallback for an empty conversation), and both
  account-purge actions delete them with the Account.
- **Hand-off:** **Contact the team** in the sheet footer opens a form (message, email prefilled with the signed-in
  user's address). A real send emails `rxdesk.support.email` synchronously (Maizzle, English chrome, Reply-To the
  customer, subject `[Support] <email> — <Plan>`, the message and context then the transcript), sets
  `escalated_at`, and shows "Message sent. We reply within 2 business days." A failed send shows an error, **Copy
  transcript** and **Email support** (mailto), and never marks the conversation.
- **Daily counters:** `support_chat_daily_counters` has one row per UTC day for conversations started, user
  messages, hand-offs, 👍/👎, and the tokens and list cost of `support_chat` ledger rows. It holds no Account id and
  no content, and has no foreign key, so pruning and account deletion leave the counts in place.
- **For the admin:** `GET /internal/v1/support/conversations` (admin token plus a valid
  `X-Internal-Admin-Email`) returns the transcripts with their messages, nothing older than 30 days of inactivity,
  and `?feedback=down` keeps only conversations with a 👎 answer.

## How to get to it (user POV)

```sh
$V/launch.sh               # stack and fixture first
$V/selling.sh up            # selling mode
$V/complimentary.sh up      # app access for the fixture Account
$V/support-chat.sh up       # Availability: service:support_chat on
```

Sign in as the fixture Owner, open the account menu, then **Help & support** → **Chat support**. The empty state
offers three suggested questions. The run needs a configured default AI-provider key and model, `service:ai` on,
Mailpit and the support email; `support-chat.sh up` only writes the Availability row. One short question makes a
real AI call for a fraction of a cent; keep it to one or two questions per run.

## Driving it with Playwright

- Journey: `$V/drive.sh support-chat` (selling mode).
- Selectors: `@sidebar-workspace-menu`, `@sidebar-support-trigger`, `@sidebar-support-chat`,
  `@support-chat-sheet`, `@support-chat-title`, `@support-chat-empty`, `@support-chat-suggestion-*`,
  `@support-chat-input`, `@support-chat-send`, `@support-chat-message-user`, `@support-chat-message-assistant`,
  `@support-chat-typing`, `@support-chat-failed`, `@support-chat-failed-retry`, `@support-chat-new`,
  `@support-chat-limit-conversation`, `@support-chat-limit-daily`, `@support-chat-limit-minute`.
- End state: the sheet is titled "Support assistant", carries the AI disclosure, shows three suggestions, and one
  typed question gets a written answer with no provider or model name and no JavaScript errors.
- Side effects: one `support_conversations` row, one user message and one answer in `support_messages`, and one
  `ai_credit_usages` row with feature `support_chat` and `credits` 0.

## The limit copy

After the journey, fill the fixture's conversation to the cap and drive it:

```sh
$V/support-chat.sh limit
$V/drive.sh support-chat-limit
```

The journey opens the sheet on `@support-chat-limit-conversation`: "This conversation has reached 20 messages.
Start a new conversation to keep going." The copy says messages; the counter counts user questions only. The input
is disabled and **New conversation** in the header clears it. The same cap applies after 40 user questions in a UTC
day, with the reset time (`@support-chat-limit-daily`). The per-minute refusal (`@support-chat-limit-minute`) has no
timer: its line stays until the next send, retry or **New conversation**. `support-chat-limit` drives the
conversation cap only; the daily and minute lines are covered by
`tests/Feature/Support/SupportChatLimitsTest.php`, not by a journey.

## The hand-off

```sh
$V/support-chat.sh clear     # a clean conversation
$V/support-chat.sh up
$V/drive.sh support-handoff
```

The journey opens the sheet, sends one short question, opens **Contact the team**, checks the email is prefilled,
sends, and checks the success copy (`@support-handoff-sent`, "We reply within 2 business days"). It then closes the
form (`@support-chat-escalated` shows the waiting line) and checks Mailpit: the email to `support@therxdesk.com`
has the subject `[Support] verify-agent@example.com — <Plan>`, Reply-To the fixture email, and the message and the
question in its text. Selectors: `@support-handoff-open`, `@support-handoff-message`, `@support-handoff-email`,
`@support-handoff-submit`, `@support-handoff-sent`, `@support-handoff-close`, `@support-chat-escalated`.

Side effects to check from the host:

```sh
$V/db.sh "select escalated_at from support_conversations where account_id = '<accountId>' and user_id = '<userId>' order by created_at desc limit 1"
$V/db.sh "select * from support_chat_daily_counters order by date desc limit 1"
```

## Checking the rows

```sh
$V/db.sh "select feature, credits, total_tokens from ai_credit_usages where account_id = '<accountId>' and feature = 'support_chat' order by created_at desc limit 1"
$V/db.sh "select count(*) from support_messages where conversation_id in (select id from support_conversations where account_id = '<accountId>' and user_id = '<userId>')"
```

The ledger row reads `support_chat | 0 | <tokens>`; the balance the account menu shows is unchanged.

## Gotchas

- The chat is off until `support-chat.sh up` writes the Availability row: a missing row means off for this service
  (unlike the networks). `support-chat.sh clear` switches it off again and deletes the fixture's conversations.
- Turning AI off (`service:ai`), removing the provider key, or setting `SELF_HOSTED=true` also closes the chat and
  removes the menu item; the HTTP tests cover those gates.
- The corpus is product documentation: update the topic a flow change touches in the same change. It never states a
  price, a Quota or an AI credit amount.
