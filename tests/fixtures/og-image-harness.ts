/**
 * Draws the public pages' Open Graph image: 1200×630, the RX mark and
 * wordmark, the landing's headline and the real queue screen captured for the
 * landing (`resources/js/assets/landing/calendar_list-desktop.webp`). It
 * needs no running app: the fonts come from the self-hosted font packages and
 * the screen from the landing captures, so redraw it whenever that capture
 * changes. Steps in `resources/js/assets/landing/CAPTURE.md`.
 *
 * Run it in the Playwright image:
 *   node tests/fixtures/og-image-harness.ts <output folder>
 */
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

import { chromium } from 'playwright';

const [outputFolder] = process.argv.slice(2);

const WIDTH = 1200;
const HEIGHT = 630;

/** The landing's hero title, in English: one image serves every locale. */
const HEADLINE = 'Every brand you manage in one place.';

const root = process.cwd();

const fileUrl = (relativePath: string): string =>
    pathToFileURL(path.join(root, relativePath)).href;

const fonts = {
    heading: fileUrl(
        'node_modules/@fontsource-variable/outfit/files/outfit-latin-wght-normal.woff2',
    ),
    logo: fileUrl(
        'node_modules/@fontsource-variable/roboto-slab/files/roboto-slab-latin-wght-normal.woff2',
    ),
};

/** The RX mark as the favicon draws it (`resources/js/lib/rxMark.ts` geometry). */
const mark = fileUrl('public/favicon.svg');

const screen = fileUrl(
    'resources/js/assets/landing/calendar_list-desktop.webp',
);

const html = `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @font-face { font-family: 'Outfit'; src: url('${fonts.heading}') format('woff2'); font-weight: 100 900; }
    @font-face { font-family: 'Roboto Slab'; src: url('${fonts.logo}') format('woff2'); font-weight: 100 900; }
    * { box-sizing: border-box; margin: 0; }
    body { width: ${WIDTH}px; height: ${HEIGHT}px; overflow: hidden; background: #f7f6f3; color: #292928; position: relative; }
    .copy { position: absolute; left: 72px; top: 72px; width: 440px; }
    .logo { display: flex; align-items: center; gap: 14px; font: 700 34px/1 'Roboto Slab'; letter-spacing: -0.01em; }
    .logo img { width: 44px; height: 44px; }
    h1 { margin-top: 96px; font: 600 60px/1.05 'Outfit'; letter-spacing: -0.02em; }
    .screen { position: absolute; left: 560px; top: 72px; width: 760px; height: 640px; overflow: hidden;
        border: 1px solid #eae8e5; border-radius: 20px; background: #fff;
        box-shadow: 0 0 1px 1px #331f0017, 0 24px 48px -16px #2e1c002e; }
    .screen img { display: block; width: 760px; height: auto; }
</style>
</head>
<body>
    <div class="copy">
        <div class="logo">
            <img src="${mark}" alt="">
            <span>The RX Desk</span>
        </div>
        <h1>${HEADLINE}</h1>
    </div>
    <div class="screen"><img src="${screen}" alt=""></div>
</body>
</html>`;

fs.mkdirSync(outputFolder, { recursive: true });

const htmlFile = path.join(outputFolder, 'og-image.html');
fs.writeFileSync(htmlFile, html);

const browser = await chromium.launch();
const page = await browser.newPage({
    viewport: { width: WIDTH, height: HEIGHT },
    deviceScaleFactor: 1,
});

await page.goto(pathToFileURL(htmlFile).href);
await page.evaluate(() => document.fonts.ready);
await Promise.all(
    (await page.locator('img').all()).map((image) =>
        image.evaluate((element: HTMLImageElement) => element.decode()),
    ),
);
await page.screenshot({ path: path.join(outputFolder, 'og-image.png') });

await browser.close();
