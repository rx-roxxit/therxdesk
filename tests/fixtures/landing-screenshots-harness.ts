/**
 * Captures the app screens the public landing shows, from the demo desk that
 * `Database\Seeders\LandingDemoSeeder` builds on the dev database. Every
 * screen is taken twice: at desktop width and at phone width, both at 2× DPR,
 * as PNGs in the output folder, with `screenshots.json` holding each capture's
 * size in CSS pixels. `resources/js/assets/landing/CAPTURE.md` lists the steps
 * (seed, capture, convert to WebP, forget the desk).
 *
 * Run it in the Playwright image on the host network, against the dev app
 * and its Vite dev server:
 *   node tests/fixtures/landing-screenshots-harness.ts <output folder> [base URL]
 */
import fs from 'node:fs';
import path from 'node:path';

import { chromium, type Page } from 'playwright';

const [outputFolder, baseUrl = 'http://localhost:8000'] = process.argv.slice(2);

const OWNER_EMAIL = 'landing-demo@example.com';
const OWNER_PASSWORD = 'password';

/** The draft typed into the editor for the composer and assistant captures. */
const DRAFT =
    'Sourdough Saturday is back. First loaves come out of the oven at 8, and the rye with caraway and orange is on the shelf all weekend.';

/** The assistant's answer is scripted, so the capture never calls the AI provider. */
const ASSISTANT_REPLY =
    'Sourdough Saturday is back. First loaves out at 8, rye with caraway and orange all weekend.';

/**
 * The composer captures use a taller window and the expanded composer, so the
 * previews column shows more than one network.
 */
const DEVICES = {
    desktop: {
        viewport: { width: 1440, height: 900 },
        composerViewport: { width: 1440, height: 1400 },
        isMobile: false,
    },
    phone: {
        viewport: { width: 390, height: 844 },
        composerViewport: { width: 390, height: 844 },
        isMobile: true,
    },
} as const;

type Device = keyof typeof DEVICES;

type Size = { width: number; height: number };

const sizes: Record<string, Partial<Record<Device, Size>>> = {};

fs.mkdirSync(outputFolder, { recursive: true });

const browser = await chromium.launch();

const capture = async (
    page: Page,
    name: string,
    device: Device,
    selector: string,
): Promise<void> => {
    const element = page.locator(selector).first();
    await element.waitFor({ state: 'visible' });
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => document.fonts.ready);

    const box = await element.boundingBox();

    if (box === null) {
        throw new Error(`${name}: ${selector} has no box.`);
    }

    await element.screenshot({
        path: path.join(outputFolder, `${name}-${device}.png`),
        animations: 'disabled',
        caret: 'hide',
    });

    sizes[name] = {
        ...sizes[name],
        [device]: {
            width: Math.round(box.width),
            height: Math.round(box.height),
        },
    };
};

/**
 * Marks every tour and the AI card as seen, so neither covers a screen.
 */
const quietFirstVisits = async (page: Page): Promise<void> => {
    await page.evaluate(() => {
        const userId = window.history.state.page.props.auth.user.id;
        const seen = { status: 'skipped', step: 1 };

        localStorage.setItem(
            `trypost:${userId}`,
            JSON.stringify({
                version: 1,
                user: {
                    tours: {
                        'publish.views': seen,
                        'insights.filters': seen,
                        'mcp.prompt': seen,
                    },
                },
                workspaces: {},
            }),
        );
    });
};

const signIn = async (page: Page): Promise<void> => {
    await page.goto(`${baseUrl}/login`);
    await page.fill('input[type=email]', OWNER_EMAIL);
    await page.fill('input[type=password]', OWNER_PASSWORD);
    await page.keyboard.press('Enter');
    await page.waitForURL(/\/schedule/);
    await quietFirstVisits(page);
};

const openComposerWithDraft = async (page: Page): Promise<void> => {
    // The dev disk refuses to serve a private upload back, so the capture
    // answers it with the very image it uploads.
    await page.route('**/storage/**', (route) =>
        route.fulfill({ path: path.join(outputFolder, 'draft-image.png') }),
    );
    await page.goto(`${baseUrl}/posts/create`);
    await page.locator('[data-testid="post-composer-dialog"]').waitFor();
    await page.locator('[data-testid="composer-all-accounts"]').click();

    // Expanded, the composer fills the window, so the previews column shows
    // more than one network (the button only exists on wide screens).
    const expand = page.locator('[data-testid="composer-expand-dialog"]');

    if (await expand.isVisible()) {
        await expand.click();
    }

    await page.locator('[data-testid="composer-base-content"]').fill(DRAFT);
    await page
        .locator('[data-testid="composer-file-input"]')
        .setInputFiles(path.join(outputFolder, 'draft-image.png'));
    await page
        .locator('[data-testid^="composer-media-item"]')
        .first()
        .waitFor();
    await page.waitForLoadState('networkidle');
};

for (const device of Object.keys(DEVICES) as Device[]) {
    const { viewport, composerViewport, isMobile } = DEVICES[device];
    const context = await browser.newContext({
        viewport,
        isMobile,
        deviceScaleFactor: 2,
        locale: 'en-US',
        timezoneId: 'UTC',
        colorScheme: 'light',
    });
    const page = await context.newPage();

    await signIn(page);

    await page.goto(`${baseUrl}/schedule`);
    await capture(page, 'calendar_list', device, 'main');

    await page.goto(`${baseUrl}/schedule/calendar/month`);
    await capture(page, 'calendar_month', device, 'main');

    await page.goto(`${baseUrl}/schedule?tab=approvals`);
    await capture(page, 'approvals', device, 'main');

    await page.setViewportSize(composerViewport);
    await openComposerWithDraft(page);
    await capture(
        page,
        'composer',
        device,
        '[data-testid="post-composer-dialog"]',
    );

    await page.route('**/posts/ai/assist', (route) =>
        route.fulfill({ json: { content: ASSISTANT_REPLY } }),
    );
    await page.locator('[data-testid="composer-ai-assistant"]').click();
    await page
        .locator('[data-testid="writing-assistant-mode-shorten"]')
        .click();
    await page
        .locator('[data-testid="writing-assistant-suggestion"]')
        .waitFor();
    await capture(
        page,
        'assistant',
        device,
        '[data-testid="post-composer-dialog"]',
    );

    await context.close();
}

await browser.close();

fs.writeFileSync(
    path.join(outputFolder, 'screenshots.json'),
    `${JSON.stringify(sizes, null, 4)}\n`,
);
