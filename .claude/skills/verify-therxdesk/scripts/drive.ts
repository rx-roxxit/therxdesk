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

import { chromium, type Frame, type Page } from 'playwright';

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

/** Wait until no CSS animation runs (a dialog fading in), so a screenshot shows the end state. */
const animationsSettled = async (page: Page): Promise<void> => {
    await page.waitForFunction(() => {
        return document
            .getAnimations()
            .every((animation) => animation.playState !== 'running');
    });
};

/** The signed-in user's email, read from the Inertia page props. */
const signedInEmail = async (page: Page): Promise<string | null> => {
    return page.evaluate(() => {
        return window.history.state?.page?.props?.auth?.user?.email ?? null;
    });
};

type Mode = 'selling' | 'self-hosted';

/**
 * The mode php-fpm serves, from the `selfHosted` Inertia prop of the page
 * open now (shared on every page, the login page included).
 */
const servedMode = async (page: Page): Promise<Mode | null> => {
    const selfHosted = await page.evaluate(() => {
        const fromHistory = window.history.state?.page?.props?.selfHosted;

        if (fromHistory !== undefined) {
            return fromHistory;
        }

        // Before Inertia writes history, the first page's JSON is in the DOM.
        const initialPage = document.querySelector('script[data-page="app"]');

        if (initialPage === null) {
            return null;
        }

        return (
            JSON.parse(initialPage.textContent ?? '{}')?.props?.selfHosted ??
            null
        );
    });

    if (selfHosted === true) {
        return 'self-hosted';
    }

    if (selfHosted === false) {
        return 'selling';
    }

    return null;
};

/** The journeys that need selling mode (selling.sh up); every other one needs self-hosted. */
const SELLING_JOURNEYS = ['selling-mode', 'welcome-checkout', 'billing'];

/** The test card Stripe's sandbox always accepts. */
const TEST_CARD = '4242424242424242';

/** An expiry two years ahead, as MM / YY. */
const testCardExpiry = (): string => {
    const year = (new Date().getUTCFullYear() + 2) % 100;

    return `12${String(year).padStart(2, '0')}`;
};

/**
 * Pay on Stripe's hosted Checkout page (checkout.stripe.com, a sandbox
 * session) with the test card, as a user does. The card form lives in a
 * Stripe Elements iframe; these selectors are Stripe's, not ours (checked
 * November 2026): when Stripe changes its page, fix them here.
 */
const payOnStripeCheckout = async (run: Run): Promise<void> => {
    const { page } = run;

    await page.waitForURL((url) => url.hostname === 'checkout.stripe.com', {
        timeout: 60_000,
    });

    // Several Stripe iframes load; the card form is the one with the number field.
    let form: Frame | null = null;

    for (let attempt = 0; attempt < 60 && form === null; attempt++) {
        for (const frame of page.frames()) {
            if ((await frame.locator('#payment-numberInput').count()) > 0) {
                form = frame;
            }
        }

        if (form === null) {
            await page.waitForTimeout(1_000);
        }
    }

    if (form === null) {
        throw new Error('the Stripe card form never appeared');
    }

    await form.locator('#payment-numberInput').waitFor();
    await run.step('stripe-checkout');

    await form.locator('#payment-numberInput').fill(TEST_CARD);
    await form.locator('#payment-expiryInput').fill(testCardExpiry());
    await form.locator('#payment-cvcInput').fill('123');
    await form.locator('#payment-nameInput').fill('Verify Agent');

    // A US billing country adds a ZIP field; the country follows the IP.
    const postalCode = form.locator('#payment-postalCodeInput');

    if (await postalCode.isVisible()) {
        await postalCode.fill('10001');
    }

    // Stripe Link offers to save the card; leave it off so no Link account is made.
    const saveWithLink = form.locator('#payment-linkOptInInput');

    if ((await saveWithLink.isVisible()) && (await saveWithLink.isChecked())) {
        await saveWithLink.uncheck();
    }

    await run.step('stripe-checkout-filled');
    await form.locator('[data-testid="payment-form-submit-button"]').click();
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

    /** features/selling-mode.md */
    'selling-mode': async (run) => {
        const { page } = run;

        await page.goto(`${run.baseUrl}/login`);
        await page.locator(tid('@login-email')).waitFor();
        run.check(
            'php-fpm serves selling mode (selfHosted is false)',
            (await servedMode(page)) === 'selling',
            await servedMode(page),
        );

        await signIn(run);
        await page.waitForLoadState('networkidle');
        await run.step('after-sign-in');

        // Only selling mode holds an Owner without a subscription at Welcome
        // (EnsureAccountReady); self-hosted opens the Schedule.
        const landedOn = new URL(page.url()).pathname;
        run.check(
            'an Owner without a subscription lands on Welcome',
            landedOn.startsWith('/welcome'),
            landedOn,
        );

        const hasActiveSubscription = await page.evaluate(() => {
            return (
                window.history.state?.page?.props?.auth
                    ?.hasActiveSubscription ?? null
            );
        });
        run.check(
            'the Account has no active subscription yet',
            hasActiveSubscription === false,
            hasActiveSubscription,
        );
    },

    /** features/welcome-checkout.md */
    'welcome-checkout': async (run) => {
        const { page } = run;

        await signIn(run);
        await page.locator(tid('@welcome-persona-continue')).waitFor();
        await run.step('welcome-persona');

        await page.locator(tid('@welcome-persona-creator')).click();
        await page.locator(tid('@welcome-persona-continue')).click();

        await page.locator(tid('@welcome-goals-continue')).waitFor();
        await page.locator(tid('@welcome-goal-save_time')).click();
        await run.step('welcome-goals');
        await page.locator(tid('@welcome-goals-continue')).click();

        await page.locator(tid('@welcome-software-continue')).waitFor();
        await page.locator(tid('@welcome-software-none')).click();
        await run.step('welcome-software');
        await page.locator(tid('@welcome-software-continue')).click();

        await page.locator(tid('@plan-card-socials')).waitFor();
        await run.step('welcome-plan');

        const price = await page
            .locator(tid('@plan-price-socials'))
            .innerText();
        run.check(
            'the Plan step shows Socials at $19',
            price.includes('$19'),
            price,
        );

        await page.locator(tid('@welcome-plan-continue')).click();
        await payOnStripeCheckout(run);

        // Back in the app, Processing waits for the webhook to give the
        // Account access, then opens the Schedule.
        await page.waitForURL((url) => url.pathname === '/billing/processing', {
            timeout: 90_000,
        });
        await run.step('processing');
        await page.waitForURL((url) => url.pathname.startsWith('/schedule'), {
            timeout: 90_000,
        });
        await page.waitForLoadState('networkidle');
        await run.step('after-checkout');

        const props = await page.evaluate(() => {
            const auth = window.history.state?.page?.props?.auth;

            return {
                hasActiveSubscription: auth?.hasActiveSubscription ?? null,
                plan: auth?.plan?.name ?? null,
            };
        });
        run.check(
            'the Account has an active subscription after the webhook',
            props.hasActiveSubscription === true,
            props,
        );
        run.check(
            'the Account is on Socials',
            props.plan === 'Socials',
            props.plan,
        );
    },

    /** features/billing.md (after welcome-checkout, on the subscribed fixture) */
    billing: async (run) => {
        const { page } = run;

        await signIn(run);
        await quietFirstVisits(run);
        await page.reload();

        await page.locator(tid('@sidebar-workspace-menu')).click();
        await page.locator(tid('@sidebar-menu-billing')).click();
        await page.waitForURL(
            (url) => url.pathname === '/settings/account/billing',
        );
        await page.locator(tid('@billing-current-plan')).waitFor();
        await page.waitForLoadState('networkidle');
        await run.step('billing');

        const currentPlan = {
            name: await page
                .locator(tid('@billing-current-plan-name'))
                .innerText(),
            price: await page
                .locator(tid('@billing-current-plan-price'))
                .innerText(),
            badge: await page
                .locator(tid('@billing-current-plan-badge'))
                .innerText(),
        };
        run.check(
            'the current plan is Socials, $19/month, Active',
            currentPlan.name.includes('Socials') &&
                currentPlan.price.includes('$19') &&
                currentPlan.badge.includes('Active'),
            currentPlan,
        );

        const card = page.locator(
            `${tid('@billing-payment-methods')} >> text=4242`,
        );
        await card.first().waitFor();
        run.check(
            'the test card is listed',
            (await card.count()) > 0,
            await card.count(),
        );

        await page.locator(tid('@billing-change-plan')).click();
        await page.locator(tid('@plan-card-workspaces')).waitFor();
        await animationsSettled(page);
        await run.step('plan-dialog');

        await page.locator(tid('@plan-interval-yearly')).click();
        await page.locator(tid('@plan-save-two-months')).waitFor();
        const yearly = await page
            .locator(tid('@plan-price-socials'))
            .innerText();
        run.check(
            'yearly shows Socials at $15.83 a month',
            yearly.includes('$15.83'),
            yearly,
        );
        await run.step('plan-dialog-yearly');

        await page.locator(tid('@plan-interval-monthly')).click();
        await page.locator(tid('@plan-select-workspaces')).click();
        await page
            .locator(tid('@billing-plan-dialog'))
            .waitFor({ state: 'detached' });
        await page
            .locator(tid('@billing-current-plan-name'))
            .filter({ hasText: 'Workspaces' })
            .waitFor({
                timeout: 60_000,
            });
        await page.waitForLoadState('networkidle');
        await run.step('after-upgrade');

        const upgraded = await page
            .locator(tid('@billing-current-plan-price'))
            .innerText();
        run.check(
            'the plan is now Workspaces at $99',
            upgraded.includes('$99'),
            upgraded,
        );

        // "Manage on Stripe" opens the sandbox Customer Portal for this Account.
        await page.locator(tid('@billing-manage-stripe')).click();
        await page.waitForURL((url) => url.hostname === 'billing.stripe.com', {
            timeout: 60_000,
        });
        // Stripe's portal keeps polling, so it never reaches networkidle:
        // wait for the subscription it shows instead.
        const portalPlan = page.getByText('$99.00 per month');
        await portalPlan.first().waitFor({ timeout: 60_000 });
        await run.step('stripe-portal');
        run.check(
            'Manage on Stripe opens the Customer Portal on the Workspaces subscription',
            new URL(page.url()).hostname === 'billing.stripe.com' &&
                (await portalPlan.count()) > 0,
            new URL(page.url()).hostname,
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
        // A journey written for the other mode fails on its first screen in
        // a confusing way: stop at once and say which mode it needs.
        const neededMode: Mode = SELLING_JOURNEYS.includes(journeyName)
            ? 'selling'
            : 'self-hosted';
        await page.goto(`${baseUrl}/login`);
        await page.locator(tid('@login-email')).waitFor();
        const mode = await servedMode(page);
        run.check(
            `the app serves ${neededMode} mode`,
            mode === neededMode,
            mode,
        );

        if (mode !== neededMode) {
            throw new Error(
                `journey ${journeyName} needs ${neededMode} mode, the app serves ${mode}: run selling.sh ${neededMode === 'selling' ? 'up' : 'down'}`,
            );
        }

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
