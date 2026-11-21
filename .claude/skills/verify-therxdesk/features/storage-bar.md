# Storage bar

The account menu shows the Owner how much media storage the Account uses against its quota, and links to the video
policy on Preferences.

## Sub-features

- Usage text "used of limit" (`@sidebar-menu-storage-usage`) and a progress bar (`@sidebar-menu-storage-progress`),
  red at 100% or more.
- The bar links to `/settings/preferences#video-policy`.
- Shown to the Account's Owner only, and only when the Account owns a Workspace (the `storage` Inertia prop is null
  otherwise). A member never sees it.

## How to get to it (user POV)

Sign in, open the account menu at the bottom of the sidebar (the user's name and Workspace), look for the storage
row. Click it to reach the video policy.

## Driving it with Playwright

- No journey yet. To write one: `signIn`, then click `@sidebar-workspace-menu`, wait for `@sidebar-menu-storage`,
  read `@sidebar-menu-storage-usage`, `run.step()`, click `@sidebar-menu-storage`, wait for the path
  `/settings/preferences` and the `#video-policy` hash.
- End state: the usage text is visible and matches the `storage` prop
  (`window.history.state.page.props.storage.used_label` / `limit_label`); after the click, Preferences shows the
  video policy section.
- Side effect: none (read-only feature). To prove a non-zero usage, upload media through the composer first.

## Gotchas

- The fixture Owner has no media, so usage reads zero; proving the red full state needs real uploads past the
  quota, not a database edit.
- The storage copy is English in every locale (an exception kept on purpose until it is translated).
