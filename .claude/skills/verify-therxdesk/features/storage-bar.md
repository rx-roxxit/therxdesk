# Storage bar

The account menu shows the Owner how much media storage the Account uses against its quota, and links to the video
policy on Preferences.

## Sub-features

- Usage text "used of limit" (`@sidebar-menu-storage-usage`) and a progress bar (`@sidebar-menu-storage-progress`),
  red at 100% (the server caps `percent` at 100).
- The bar links to `/settings/preferences#video-policy`.
- Shown to the Account's Owner only, and only when the Account owns a Workspace (the `storage` Inertia prop is null
  otherwise). A member never sees it.

## How to get to it (user POV)

Sign in, open the account menu at the bottom of the sidebar (the user's name and Workspace), look for the storage
row. Click it to reach the video policy.

## Driving it with Playwright

- Journey: `$V/drive.sh storage-bar`.
- Selectors: `@sidebar-workspace-menu`, `@sidebar-menu-storage`, `@sidebar-menu-storage-usage`,
  `@sidebar-menu-storage-progress`. The Video policy row is `#video-policy`; its select carries
  `@preferences-video-policy-trigger` (a `SettingsSelect` puts the test id on its trigger).
- End state: the usage text matches the `storage` prop
  (`window.history.state.page.props.storage.used_label` / `limit_label`); after the click the URL is
  `/settings/preferences#video-policy` and the Video policy row is visible.
- Side effect: none (read-only feature).

## Gotchas

- The fixture Owner has no media, so usage reads zero. To prove the red state, upload media and lower the Quota
  under the usage with `php artisan rxdesk:quota set verify-agent@example.com 1MB` (then
  `... default` to restore): `percent` caps at 100 and the bar turns red. A database edit is not the way.
- The storage copy is translated in all 16 locales (`rxdesk.storage_bar.label` / `.usage`). `StorageBarPropTest`
  still asserts the English string for every locale and is red on `main`; that is a test to update, not a behavior
  of this feature.
