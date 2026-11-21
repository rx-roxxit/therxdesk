# Compose a post and save it as a draft

The composer writes one post for one or more channels, with a live preview per network, and saves it as a draft
without a date.

## Sub-features

- Open the composer from "New post" (`@posts-new-post`) on the Schedule's List and Board views or on a channel's
  Grid.
- Pick channels (`@composer-add-account`, then `@composer-account-option-<channel id>`).
- Write the caption per channel (`@composer-caption-<channel id>`) or the shared text (`@composer-base-content`).
- Live network preview (LinkedIn preview for the fixture channel).
- Save draft (`@composer-save-draft`), Publish now / schedule (not drivable: would call the network), Create
  another, Labels, Templates, AI Assistant, media.
- An unfinished post is kept in browser storage and offered back on the next open (`@composer-resume-dialog`).

## How to get to it (user POV)

Sign in, open the Schedule, switch to the List view, press "New post". Pick the channel, type, press "Save draft".
The dialog closes and the list opens on the Drafts tab, with the draft under "Unscheduled".

## Driving it with Playwright

- Journey: `$V/drive.sh compose-draft`. The caption is `Verify draft <ISO time>`, recorded in
  `result.json` → `notes.caption`.
- The view switch has no test id: the journey clicks `getByRole('link', { name: 'List', exact: true })` and waits
  for the path `/schedule`.
- End state: the URL is `/schedule?tab=drafts` and a card with the caption is visible (`05-draft-in-list.png`).
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
