# Switch the app language

A signed-in user changes the app language from the account menu; the page translates in place, without a reload,
and the choice is saved on the user.

## Sub-features

- Language submenu in the sidebar account menu (`@sidebar-language-trigger`, then
  `@sidebar-language-<code>`, e.g. `@sidebar-language-ja`), 16 locales. The trigger reads "Language: English".
- Language picker on the auth pages (login, register) for guests (`LanguageSelect`): `@language-picker-trigger`,
  `@language-picker-option-<code>`.
- The calendar header and dates follow the new locale.

## How to get to it (user POV)

Sign in, open the account menu at the bottom of the sidebar, hover "Language: English", pick one.

## Driving it with Playwright

- Journey: `$V/drive.sh language-switch`.
- The journey sets `window.__notReloaded = true` before the switch and clicks `@sidebar-workspace-menu`, dispatches
  a `pointermove` on `@sidebar-language-trigger` then clicks it (a Radix sub-trigger does not open on a click
  alone), and clicks `@sidebar-language-ja`.
- End state: the sidebar's Publish link reads 公開, the shared `locale` prop is `ja`, and `window.__notReloaded`
  is still true (a full page load would clear it).
- Side effect: `$V/db.sh "select locale from users where email = 'verify-agent@example.com'"` shows `ja`.

## Gotchas

- The change sticks on the fixture Owner, and later journeys select by English names: run `fixture.sh up` after
  this journey to reset the locale.
- See `tests/Browser/SidebarLanguageSwitchTest.php` for the exact pointer sequence.
