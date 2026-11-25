# Compose a post and save it as a draft

The composer writes one draft per selected channel (a two-channel post saves two rows sharing a `post_group_id`),
with a live preview per network, and saves it without a date.

## Sub-features

- Open the composer from "New post" (`@posts-new-post`) on the Schedule's List and Board views or on a channel's
  Grid; it also opens from the sidebar's New menu and a calendar slot.
- Pick channels (`@composer-add-account`, then `@composer-account-option-<channel id>`).
- Write the caption per network group (`@composer-caption-<anchor channel id>`) or the shared text
  (`@composer-base-content`); several accounts on one network share one customization card.
- Live network preview (LinkedIn preview for the fixture channel).
- Save draft (`@composer-save-draft`), Publish now / schedule, Create another, Labels, Templates, media. Future
  scheduling is safe in the fixture; **Publish now** would call the network. The AI Assistant button
  (`@composer-ai-assistant`) renders only while AI is installed and switched on (`aiEnabled`).
- A selected channel whose network is switched off shows `@platform-unavailable-banner`; see `availability.md`.
- An unfinished post is kept in browser storage and offered back on the next open (`@composer-resume-dialog`).

## How to get to it (user POV)

Sign in, open the Schedule, switch to the List view, press "New post". Pick the channel, type, press "Save draft".
The dialog closes and the list opens on the Drafts tab, with the draft under "Unscheduled".

## Driving it with Playwright

- Journey: `$V/drive.sh compose-draft`. The caption is `Verify draft <ISO time>`, recorded in
  `result.json` → `notes.caption`.
- The view switch exposes `@schedule-view-<key>` (`@schedule-view-list`, `-calendar`, `-board`, and `-grid` only when
  the page is scoped to one channel whose Instagram-family profile has a grid); the journey clicks
  `@schedule-view-list` and waits for the path `/schedule`. The LinkedIn fixture cannot open a Grid entry.
- End state: the URL is `/schedule?tab=drafts` and a card with the caption is visible (`05-draft-in-list.png`).
- The journey proves the List entry, one LinkedIn channel and Save draft. Board, Grid, sidebar, calendar-slot,
  multi-channel grouping, previews, resume, AI, labels and media are separate paths in this feature; prove one of
  those explicitly when a change touches it.
- Side effect, from the host:
  `$V/db.sh "select id, status, content from posts where workspace_id = '<workspaceId>'"` shows a row with
  `status = draft` and `content` equal to the caption.

## Gotchas

- The List view paints skeleton cards first: wait for the caption's card before the end-state screenshot.
- Drafts from earlier runs stay until `fixture.sh up` or `cleanup.sh`, so check for this run's caption, not a count
  of drafts.
- The channel's token is fake. Never press "Publish now" or schedule into the past: Horizon and the scheduler are
  live and would call LinkedIn.
- The channel menu stays open over the editor after picking a channel; it does not block typing the caption.
