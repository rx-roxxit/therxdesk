# Font licences

The app and the public pages serve every face from this build. The files come
from the `@fontsource-variable/*` packages, imported by `resources/css/app.css`,
so the browser downloads them from the app's own origin and contacts no font
host (no `fonts.googleapis.com` / `fonts.gstatic.com` request). The emails are
the one exception: they keep their `<link>` to Google Fonts, since an email
client fetches what its reader's inbox serves.

| Face | Used for | Package | Licence |
| --- | --- | --- | --- |
| Inter | body text (`--font-sans`) | `@fontsource-variable/inter` | SIL Open Font License 1.1 — Copyright 2016 The Inter Project Authors (https://github.com/rsms/inter) |
| Outfit | headings (`--font-heading`) | `@fontsource-variable/outfit` | SIL Open Font License 1.1 — Copyright 2021 The Outfit Project Authors (https://github.com/Outfitio/Outfit-Fonts) |
| JetBrains Mono | code and tokens (`--font-mono`) | `@fontsource-variable/jetbrains-mono` | SIL Open Font License 1.1 — Copyright 2020 The JetBrains Mono Project Authors (https://github.com/JetBrains/JetBrainsMono) |
| Roboto Slab | the logo (`--font-logo`) | `@fontsource-variable/roboto-slab` | Apache License 2.0 |

Roboto Slab is the one face that is not OFL: Google ships it under the Apache
License 2.0, which is why its row says so rather than repeating OFL for all
four. All four are permissive and compatible with this repository's AGPL-3.0;
they cover the font files only, not the application.

The full text of each licence is in the package it comes from,
`node_modules/@fontsource-variable/<face>/LICENSE` after `npm ci`. The font
files themselves are emitted into `public/build/assets` as `.woff2`.
