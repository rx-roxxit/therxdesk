/**
 * The verify-therxdesk harness and its journeys, in one file (the repo's
 * ESLint refuses relative imports, so the journeys cannot import a shared
 * module). drive.sh runs it in the Playwright image:
 *
 *   node drive.ts <journey> <evidence folder>
 *
 * with the fixture (fixture.sh show) in VERIFY_FIXTURE. Every run writes to
 * the evidence folder:
 *   NN-<step>.png   a full-page screenshot after each `run.step()`
 *   trace.zip       the Playwright trace (every action, DOM snapshots, network)
 *   console.log     every console message and page error
 *   result.json     the steps, each check with its observed value, pass/fail
 *
 * A journey drives the UI only, as a user would. Side effects in the database
 * are checked afterwards from the host with db.sh, never from here.
 */
import fs from 'node:fs';
import path from 'node:path';

import { chromium, type Page } from 'playwright';

type Fixture = {
    email: string;
    password: string;
    userId: string;
    accountId: string;
    workspaceId: string;
    channelId: string;
    channelPlatform: string;
};

type Check = { name: string; passed: boolean; observed: unknown };

type Run = {
    page: Page;
    fixture: Fixture;
    baseUrl: string;
    /** Screenshot the page as the next numbered step. */
    step: (name: string) => Promise<void>;
    /** Record a check; a failed check fails the journey at the end. */
    check: (name: string, passed: boolean, observed?: unknown) => void;
    /** Record a value the host-side checks need (a created post's id). */
    note: (key: string, value: unknown) => void;
};

/** `@name` stands for `[data-testid="name"]`, as in the Pest browser tests. */
const tid = (selector: string): string => {
    return selector.replace(/@([\w-]+)/g, '[data-testid="$1"]');
};

/** Sign in through the login form, as a user does. */
const signIn = async (run: Run): Promise<void> => {
    const { page } = run;

    await page.goto(`${run.baseUrl}/login`);
    await page.locator(tid('@login-email')).fill(run.fixture.email);
    await page.locator(tid('@login-password')).fill(run.fixture.password);
    await page.locator(tid('@login-submit')).click();
    await page.waitForURL((url) => url.pathname !== '/login');
};

/**
 * Mark the first-visit tours as seen, so none covers the page. Browser
 * storage only: the account and its data are untouched.
 */
const quietFirstVisits = async (run: Run): Promise<void> => {
    await run.page.evaluate((userId) => {
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
    }, run.fixture.userId);
};

/** The signed-in user's email, read from the Inertia page props. */
const signedInEmail = async (page: Page): Promise<string | null> => {
    return page.evaluate(() => {
        return window.history.state?.page?.props?.auth?.user?.email ?? null;
    });
};

/**
 * The journeys. Each one is a user path from the feature map
 * (features/<name>.md); add a journey here and a row in features/README.md.
 */
const JOURNEYS: Record<string, (run: Run) => Promise<void>> = {
    /** features/sign-in.md */
    'sign-in': async (run) => {
        const { page } = run;

        await page.goto(`${run.baseUrl}/login`);
        await page.locator(tid('@login-email')).waitFor();
        await run.step('login-form');

        await page.locator(tid('@login-email')).fill(run.fixture.email);
        await page.locator(tid('@login-password')).fill(run.fixture.password);
        await run.step('login-filled');

        await page.locator(tid('@login-submit')).click();
        await page.waitForURL((url) => url.pathname !== '/login');
        await page.waitForLoadState('networkidle');
        await run.step('after-sign-in');

        // The Schedule opens on the view the user picked last (Calendar for a
        // new user), so any /schedule path is the Schedule.
        const landedOn = new URL(page.url()).pathname;
        run.check(
            'lands on the Schedule',
            landedOn.startsWith('/schedule'),
            landedOn,
        );

        const email = await signedInEmail(page);
        run.check(
            'signed in as the fixture Owner',
            email === run.fixture.email,
            email,
        );
    },

    /** features/compose-draft.md */
    'compose-draft': async (run) => {
        const { page, fixture } = run;
        const caption = `Verify draft ${new Date().toISOString()}`;
        run.note('caption', caption);

        await signIn(run);
        await quietFirstVisits(run);
        await page.reload();

        // A new user lands on the Calendar view; the List view holds the
        // New Post button the browser tests use. The view switch has no test
        // id, so it is reached by role and its English name (the fixture
        // Owner's locale is the default, English).
        await page.getByRole('link', { name: 'List', exact: true }).click();
        await page.waitForURL((url) => url.pathname === '/schedule');
        await page.locator(tid('@posts-new-post')).waitFor();
        await run.step('schedule');

        await page.locator(tid('@posts-new-post')).click();
        await page.locator(tid('@post-composer-dialog')).waitFor();
        await page.locator(tid('@composer-base-content')).waitFor();
        await run.step('composer-open');

        await page.locator(tid('@composer-add-account')).click();
        await page
            .locator(tid(`@composer-account-option-${fixture.channelId}`))
            .click();
        await page
            .locator(tid(`@composer-caption-${fixture.channelId}`))
            .fill(caption);
        await run.step('composer-filled');

        await page.locator(tid('@composer-save-draft')).click();
        await page
            .locator(tid('@post-composer-dialog'))
            .waitFor({ state: 'detached' });
        await run.step('after-save');

        // The list paints skeletons first: wait for the card itself before the
        // end-state screenshot.
        const card = page.getByText(caption, { exact: false });
        await card.first().waitFor();
        await run.step('draft-in-list');
        run.check(
            'the saved draft shows in the list',
            (await card.count()) > 0,
            await card.count(),
        );

        const landedOn = new URL(page.url()).href;
        run.check(
            'the list is on the Drafts tab',
            landedOn.includes('drafts'),
            landedOn,
        );
    },
};

const main = async (): Promise<void> => {
    const [journeyName, outputFolder] = process.argv.slice(2);
    const journey = JOURNEYS[journeyName];

    if (journey === undefined || outputFolder === undefined) {
        console.error(
            `usage: node drive.ts <${Object.keys(JOURNEYS).join('|')}> <evidence folder>`,
        );
        process.exit(2);
    }

    const fixture = JSON.parse(
        process.env.VERIFY_FIXTURE ?? 'null',
    ) as Fixture | null;
    const baseUrl = process.env.VERIFY_BASE_URL ?? 'http://localhost:8000';

    if (fixture === null) {
        console.error(
            'VERIFY_FIXTURE is empty: run drive.sh, not node directly',
        );
        process.exit(2);
    }

    fs.mkdirSync(outputFolder, { recursive: true });

    const browser = await chromium.launch();
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'en-US',
        timezoneId: 'UTC',
    });
    await context.tracing.start({ screenshots: true, snapshots: true });
    const page = await context.newPage();
    page.setDefaultTimeout(30_000);

    const consoleLines: string[] = [];
    const pageErrors: string[] = [];
    page.on('console', (message) => {
        consoleLines.push(`[${message.type()}] ${message.text()}`);
    });
    page.on('pageerror', (error) => {
        pageErrors.push(error.message);
        consoleLines.push(`[pageerror] ${error.message}`);
    });

    const steps: string[] = [];
    const checks: Check[] = [];
    const notes: Record<string, unknown> = {};

    const run: Run = {
        page,
        fixture,
        baseUrl,
        step: async (stepName) => {
            const file = `${String(steps.length + 1).padStart(2, '0')}-${stepName}.png`;
            await page.screenshot({
                path: path.join(outputFolder, file),
                fullPage: true,
            });
            steps.push(file);
        },
        check: (checkName, passed, observed = null) => {
            checks.push({ name: checkName, passed, observed });
        },
        note: (key, value) => {
            notes[key] = value;
        },
    };

    let crash: string | null = null;

    try {
        await journey(run);
    } catch (error) {
        crash = String(
            error instanceof Error ? (error.stack ?? error.message) : error,
        );
        await run.step('crash').catch(() => undefined);
    }

    run.check(
        'no JavaScript errors on the page',
        pageErrors.length === 0,
        pageErrors,
    );

    await context.tracing.stop({ path: path.join(outputFolder, 'trace.zip') });
    await browser.close();

    const passed = crash === null && checks.every((check) => check.passed);
    fs.writeFileSync(
        path.join(outputFolder, 'console.log'),
        consoleLines.join('\n'),
    );
    fs.writeFileSync(
        path.join(outputFolder, 'result.json'),
        JSON.stringify(
            {
                journey: journeyName,
                passed,
                crash,
                baseUrl,
                fixture: { ...fixture, password: undefined },
                finishedAt: new Date().toISOString(),
                steps,
                checks,
                notes,
            },
            null,
            2,
        ),
    );

    for (const check of checks) {
        console.log(`${check.passed ? 'ok  ' : 'FAIL'} ${check.name}`);
    }

    if (crash !== null) {
        console.log(`CRASH ${crash}`);
    }

    console.log(`${passed ? 'PASSED' : 'FAILED'} ${journeyName}`);
    process.exit(passed ? 0 : 1);
};

await main();
