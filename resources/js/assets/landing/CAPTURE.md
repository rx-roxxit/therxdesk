# Landing screenshots

The app screens on the public landing are real captures of a demo desk: three
made-up brands (Juniper Bakery, Northfield Cycles, Lumen Yoga), never customer
data. Re-capture every screen whenever the UI it shows changes.

| File               | Screen                                                             | Alt text                                   |
| ------------------ | ------------------------------------------------------------------ | ------------------------------------------ |
| `calendar_list-*`  | Queue list, a teammate's post waiting for approval in an open slot | `rxdesk.public.screenshots.calendar_list`  |
| `composer-*`       | Editor with three channels and the live previews                   | `rxdesk.public.screenshots.composer`       |
| `calendar_month-*` | Month calendar                                                     | `rxdesk.public.screenshots.calendar_month` |
| `approvals-*`      | Approvals tab                                                      | `rxdesk.public.screenshots.approvals`      |
| `assistant-*`      | AI Assistant shortening the draft                                  | `rxdesk.public.screenshots.assistant`      |

Each screen has a desktop capture (1440 px window) and a phone capture (390 px
window), both at 2× DPR. `screenshots.json` holds their sizes in CSS pixels and
`resources/js/lib/landingScreenshots.ts` reads it.

## Steps

Run against the dev stack (`docker compose up -d`, app on port 8000), never
production. `R="docker compose run --rm -T -u app app"`.

1. Seed the demo desk on the dev database:
   `$R php artisan db:seed --class=LandingDemoSeeder`
2. Make the image the composer capture uploads, in an empty output folder
   (`$OUT`):
   `docker compose run --rm -T -u app -v "$OUT":/out app magick -size 1080x1080 xc:'#F3E3C8' -fill '#C0763B' -draw 'circle 540,500 540,200' -fill '#7A4A2A' -draw 'rectangle 180,900 900,940' /out/draft-image.png`
3. Capture, in the Playwright image matching `package.json` (1.61.1), on the
   host network:
   `docker run --rm --network host -v "$PWD":/work:ro -v therxdesk_app-node-modules:/work/node_modules:ro -v "$OUT":/out -w /work mcr.microsoft.com/playwright:v1.61.1-noble node tests/fixtures/landing-screenshots-harness.ts /out`
4. Convert to WebP into this folder and copy the sizes:
   `docker compose run --rm -T -u app -v "$OUT":/in:ro app sh -c 'for f in /in/*-desktop.png /in/*-phone.png; do magick "$f" -strip -quality 80 "resources/js/assets/landing/$(basename "$f" .png).webp"; done; cp /in/screenshots.json resources/js/assets/landing/'`
5. Remove the demo desk, so the dev scheduler never tries to publish it:
   `$R php artisan tinker --execute 'Database\Seeders\LandingDemoSeeder::forget();'`
6. Look at every image, update the alt text in all 16 `lang/*/rxdesk.php` if
   what a screen shows changed, and run `npm run build`.

## What the capture fakes

- The assistant's answer is scripted (`ASSISTANT_REPLY` in the harness), so
  the capture never calls the AI provider. The panel and its buttons are real.
- The dev disk does not serve a private upload back, so the harness answers
  `/storage/...` with the same image it uploaded.
- Tours and the AI card are marked as seen in the browser before the capture.

## The Open Graph image

`og-image.jpg` (1200×630) is what link previews show for every public page:
the RX mark, the landing's headline and the `calendar_list` desktop capture.
`tests/fixtures/og-image-harness.ts` draws it from the committed capture and
the self-hosted fonts, so it needs no running app. Redraw it whenever that
capture changes, in an empty output folder (`$OUT`):

1. `docker run --rm -v "$PWD":/work:ro -v therxdesk_app-node-modules:/work/node_modules:ro -v "$OUT":/out -w /work mcr.microsoft.com/playwright:v1.61.1-noble node tests/fixtures/og-image-harness.ts /out`
2. `docker compose run --rm -T -u app -v "$OUT":/in:ro app magick /in/og-image.png -strip -quality 85 -sampling-factor 4:4:4 resources/js/assets/landing/og-image.jpg`
3. Look at it and run `npm run build` (Vite serves it as a build asset).
