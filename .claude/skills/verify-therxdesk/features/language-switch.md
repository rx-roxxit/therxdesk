# Switch the app language

A signed-in user changes the app language from the account menu; the page translates in place, without a reload,
and the choice is saved on the user.

## Sub-features

- Language submenu in the sidebar account menu (`@sidebar-language-trigger`, then
  `@sidebar-language-<code>`, e.g. `@sidebar-language-ja`), 16 locales.
- Language picker on the auth pages (login, register) for guests (`LanguageSelect`, `@<id>-trigger`,
  `@<id>-option-<code>`).
- The calendar header and dates follow the new locale.

## How to get to it (user POV)

Sign in, open the account menu at the bottom of the sidebar, hover "Language", pick one.

## Driving it with Playwright

- No journey yet. To write one: `signIn`, click `@sidebar-workspace-menu`, dispatch a `pointermove` on
  `@sidebar-language-trigger` and click it (a Radix sub-trigger does not open on a click alone), click
  `@sidebar-language-ja`. Set `window.__notReloaded = true` before, and check it is still true after, to prove no
  reload.
- End state: the sidebar's "Posts" group label reads in Japanese (`__('sidebar.groups.posts', [], 'ja')`).
- Side effect: `$V/db.sh "select locale from users where email = 'verify-agent@example.com'"` shows `ja`.

## Gotchas

- The change sticks on the fixture Owner, and later journeys select by English names: run `fixture.sh up` after
  this journey to reset the locale.
- See `tests/Browser/SidebarLanguageSwitchTest.php` for the exact pointer sequence.
