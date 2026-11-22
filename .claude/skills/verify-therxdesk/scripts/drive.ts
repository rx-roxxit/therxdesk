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

/**
 * Records every image a canvas draws from now on in `window.__images`, so a
 * test can tell which photo the share card actually painted (`drawImage` is
 * only called for an image that loaded, CORS included).
 */
const hookCanvasImages = async (page: Page): Promise<void> => {
    await page.evaluate(() => {
        const state = window as unknown as { __images?: string[] };

        if (state.__images !== undefined) {
            return;
        }

        state.__images = [];

        const drawImage = CanvasRenderingContext2D.prototype.drawImage as (
            ...args: unknown[]
        ) => void;

        CanvasRenderingContext2D.prototype.drawImage = function (
            this: CanvasRenderingContext2D,
            ...args: unknown[]
        ): void {
            state.__images?.push(
                String((args[0] as { src?: string }).src ?? ''),
            );
            drawImage.apply(this, args);
        };
    });
};

/** The signed-in user's email, read from the Inertia page props. */
const signedInEmail = async (page: Page): Promise<string | null> => {
    return page.evaluate(() => {
        return window.history.state?.page?.props?.auth?.user?.email ?? null;
    });
};

type Mode = 'selling' | 'self-hosted';

/** The register journey's address, from env.sh; `%` becomes this run's unique part. */
const guestEmail = (): string => {
    const like = process.env.VERIFY_GUEST_EMAIL_LIKE;

    if (like === undefined || like === '') {
        throw new Error(
            'VERIFY_GUEST_EMAIL_LIKE is empty: run drive.sh, not node directly',
        );
    }

    return like.replace('%', String(Date.now()));
};

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
const SELLING_JOURNEYS = [
    'register',
    'selling-mode',
    'welcome-checkout',
    'billing',
    'media-on-r2',
];

/** The test card Stripe's sandbox always accepts. */
const TEST_CARD = '4242424242424242';

/** A 1×1 PNG, the image the media-on-R2 proof uploads (`tests/fixtures/1x1.png`). */
const PROOF_IMAGE = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av6jNYGEAAAAAElFTkSuQmCC',
    'base64',
);

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

        // The login form redirects to the Calendar route; the page's `view`
        // prop defaults to week, so any /schedule path is the Schedule.
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

    /** features/registration.md */
    register: async (run) => {
        const { page } = run;

        const email = guestEmail();
        run.note('signupEmail', email);

        await page.goto(`${run.baseUrl}/register`);
        await page.locator(tid('@register-user-button')).waitFor();
        await page.waitForLoadState('networkidle');
        await run.step('register-form');

        const preview = page.locator(tid('@auth-preview-image'));
        const reviews = page.locator(tid('@auth-reviews'));
        run.check(
            'the auth screen shows the app screenshot, not reviews',
            (await preview.count()) === 1 &&
                (await preview.evaluate(
                    (image) => (image as HTMLImageElement).naturalWidth > 0,
                )) &&
                (await reviews.count()) === 0,
            { previews: await preview.count(), reviews: await reviews.count() },
        );

        const legal = await page.evaluate(() => {
            const props = window.history.state?.page?.props as
                | { legal?: { terms?: string; privacy?: string } }
                | undefined;

            return {
                terms: props?.legal?.terms ?? null,
                privacy: props?.legal?.privacy ?? null,
                links: [
                    ...document.querySelectorAll(
                        '[data-testid="legal-links"] a',
                    ),
                ].map((link) => link.getAttribute('href')),
            };
        });
        run.check(
            'Terms and Privacy follow the LEGAL_* URLs',
            legal.links.length === 2 &&
                legal.links.includes(legal.terms) &&
                legal.links.includes(legal.privacy),
            legal,
        );

        const codeField = page.locator(tid('@register-signup-code'));
        const namedCodeField = page.locator('input[name="signup_code"]');
        const codeCopy = page.getByText('Signup code', { exact: false });

        run.check(
            'the register form has no Signup code field',
            (await codeField.count()) === 0 &&
                (await namedCodeField.count()) === 0,
            {
                testId: await codeField.count(),
                named: await namedCodeField.count(),
            },
        );
        run.check(
            'the page says nothing about a Signup code',
            (await codeCopy.count()) === 0,
            await codeCopy.count(),
        );

        await page.locator(tid('@register-name')).fill('Verify Signup');
        await page.locator(tid('@register-email')).fill(email);
        await page
            .locator(tid('@register-password'))
            .fill('Tr7#pass-Word-2026');
        await run.step('register-filled');

        await page.locator(tid('@register-user-button')).click();
        await page.waitForURL((url) => url.pathname.startsWith('/welcome'), {
            timeout: 30_000,
        });
        await page.waitForLoadState('networkidle');
        await run.step('after-sign-up');

        const signedIn = await signedInEmail(page);
        run.check('the new Owner is signed in', signedIn === email, signedIn);

        const landedOn = new URL(page.url()).pathname;
        run.check(
            'a new Owner lands on Welcome',
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
            'the new Account has no subscription yet',
            hasActiveSubscription === false,
            hasActiveSubscription,
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
        // New Post button the browser tests use.
        await page.locator(tid('@schedule-view-list')).click();
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

        await page.locator(tid('@plan-card-mini')).waitFor();
        run.check(
            'the plan step has no reviews',
            (await page.locator(tid('@welcome-plan-reviews')).count()) === 0,
            await page.locator(tid('@welcome-plan-reviews')).count(),
        );
        await run.step('welcome-plan');

        const price = await page.locator(tid('@plan-price-mini')).innerText();
        run.check(
            'the Plan step shows Mini at $11.90',
            price.includes('$11.90'),
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
        run.check('the Account is on Mini', props.plan === 'Mini', props.plan);
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
            'the current plan is Mini, $11.90/month, Active',
            currentPlan.name.includes('Mini') &&
                currentPlan.price.includes('$11.90') &&
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
        await page.locator(tid('@plan-card-standard')).waitFor();
        await animationsSettled(page);
        await run.step('plan-dialog');

        await page.locator(tid('@plan-interval-yearly')).click();
        await page.locator(tid('@plan-save-two-months')).waitFor();
        const yearly = await page.locator(tid('@plan-price-mini')).innerText();
        run.check(
            'yearly shows Mini at $9.92 a month',
            yearly.includes('$9.92'),
            yearly,
        );
        await run.step('plan-dialog-yearly');

        await page.locator(tid('@plan-interval-monthly')).click();
        await page.locator(tid('@plan-select-standard')).click();
        await page
            .locator(tid('@billing-plan-dialog'))
            .waitFor({ state: 'detached' });
        await page
            .locator(tid('@billing-current-plan-name'))
            .filter({ hasText: 'Standard' })
            .waitFor({
                timeout: 60_000,
            });
        await page.waitForLoadState('networkidle');
        await run.step('after-upgrade');

        const upgraded = await page
            .locator(tid('@billing-current-plan-price'))
            .innerText();
        run.check(
            'the plan is now Standard at $17.90',
            upgraded.includes('$17.90'),
            upgraded,
        );

        // "Manage on Stripe" opens the sandbox Customer Portal for this Account.
        await page.locator(tid('@billing-manage-stripe')).click();
        await page.waitForURL((url) => url.hostname === 'billing.stripe.com', {
            timeout: 60_000,
        });
        // Stripe's portal keeps polling, so it never reaches networkidle:
        // wait for the subscription it shows instead.
        const portalPlan = page.getByText('$17.90 per month');
        await portalPlan.first().waitFor({ timeout: 60_000 });
        await run.step('stripe-portal');
        run.check(
            'Manage on Stripe opens the Customer Portal on the Standard subscription',
            new URL(page.url()).hostname === 'billing.stripe.com' &&
                (await portalPlan.count()) > 0,
            new URL(page.url()).hostname,
        );
    },

    /**
     * features/media-on-r2.md
     *
     * Needs `selling.sh up` with the proof overlay (FILESYSTEM_DISK=r2), a
     * subscribed fixture (run `welcome-checkout` once), and `media.sh seed`.
     * Proves the share card draws a channel avatar from the media host and
     * that an image and a >5 MiB video uploaded through the composer are on
     * that host, the video intact after its multipart upload.
     */
    'media-on-r2': async (run) => {
        const { page } = run;

        await signIn(run);
        await quietFirstVisits(run);
        await page.reload();

        // The Insights share card draws the seeded channel's avatar from R2.
        await page.goto(`${run.baseUrl}/insights`);
        const followers = page.locator(tid('@share-followers'));
        await followers.waitFor();
        await page.waitForFunction(
            () =>
                document
                    .querySelector('[data-testid="share-followers"]')
                    ?.getAttribute('data-state') === 'ready',
            undefined,
            { timeout: 30_000 },
        );
        await run.step('insights');

        await hookCanvasImages(page);
        await followers.click();
        await page.waitForFunction(
            () =>
                document
                    .querySelector('[data-testid="share-chart-preview"]')
                    ?.getAttribute('data-state') === 'ready',
            undefined,
            { timeout: 30_000 },
        );
        await animationsSettled(page);
        await run.step('share-card');

        const drawn = await page.evaluate(() => {
            return (
                (window as unknown as { __images?: string[] }).__images ?? []
            );
        });
        const avatarUrl =
            drawn.find(
                (source) =>
                    source.startsWith('http') && source.includes('cors=1'),
            ) ?? null;

        run.check(
            'the share card drew the channel avatar fetched with CORS',
            avatarUrl !== null,
            { avatarUrl, drawn },
        );
        run.note('shareCardAvatarUrl', avatarUrl);

        const mediaHost = avatarUrl === null ? null : new URL(avatarUrl).host;
        run.note('mediaHost', mediaHost);

        await page.keyboard.press('Escape');
        await page
            .locator(tid('@share-chart-dialog'))
            .waitFor({ state: 'detached' })
            .catch(() => undefined);

        // A new post's composer, with the shared editor's media tray. The
        // List view lives on the Schedule, not on Insights.
        await page.goto(`${run.baseUrl}/schedule`);
        await page.getByRole('link', { name: 'List', exact: true }).click();
        await page.waitForURL((url) => url.pathname === '/schedule');
        await page.locator(tid('@posts-new-post')).waitFor();
        await page.locator(tid('@posts-new-post')).click();
        await page.locator(tid('@post-composer-dialog')).waitFor();
        await page
            .locator(tid('@composer-file-input'))
            .waitFor({ state: 'attached' });
        await run.step('composer-open');

        const completed: Array<{
            url: string;
            type: string;
            size: number;
        }> = [];
        const chunks: number[] = [];

        page.on('response', (response) => {
            if (
                !response.url().includes('/media/chunked') ||
                response.request().method() !== 'POST'
            ) {
                return;
            }

            void response
                .json()
                .then(
                    (body: {
                        done?: boolean;
                        url?: string;
                        type?: string;
                        size?: number;
                    }) => {
                        if (
                            body.done === true &&
                            typeof body.url === 'string'
                        ) {
                            completed.push({
                                url: body.url,
                                type: String(body.type),
                                size: Number(body.size),
                            });
                        }
                    },
                )
                .catch(() => undefined);
        });

        page.on('request', (request) => {
            if (
                !request.url().includes('/media/chunked') ||
                request.method() !== 'POST'
            ) {
                return;
            }

            const match = request.headers()['content-range']?.match(/\/(\d+)$/);

            if (match !== undefined) {
                chunks.push(Number(match[1]));
            }
        });

        const videoSize = 6 * 1024 * 1024;
        const video = Buffer.alloc(videoSize);
        video.write('\0\0\0\x18ftypmp42\0\0\0\0mp42isom', 0, 'binary');

        await page.locator(tid('@composer-file-input')).setInputFiles([
            {
                name: 'r2-proof.png',
                mimeType: 'image/png',
                buffer: PROOF_IMAGE,
            },
            {
                name: 'r2-proof.mp4',
                mimeType: 'video/mp4',
                buffer: video,
            },
        ]);

        for (
            let attempt = 0;
            attempt < 600 && completed.length < 2;
            attempt++
        ) {
            await page.waitForTimeout(100);
        }

        await page.waitForFunction(
            () =>
                document.querySelectorAll('[data-testid="composer-media-item"]')
                    .length >= 2,
            undefined,
            { timeout: 30_000 },
        );
        await run.step('uploads-done');

        const image = completed.find((item) => item.type === 'image') ?? null;
        const clip = completed.find((item) => item.type === 'video') ?? null;

        run.check('the image upload completed', image !== null, completed);
        run.check('the video upload completed', clip !== null, completed);
        run.check(
            'both uploads are served from the media host',
            image !== null &&
                clip !== null &&
                mediaHost !== null &&
                new URL(image.url).host === mediaHost &&
                new URL(clip.url).host === mediaHost,
            { mediaHost, image, clip },
        );

        const videoChunks = chunks.filter(
            (total) => total === videoSize,
        ).length;
        run.check(
            'the video was sent in more than one chunk',
            videoChunks >= 2,
            videoChunks,
        );
        run.note('imageUrl', image?.url ?? null);
        run.note('videoUrl', clip?.url ?? null);
        run.note('videoChunks', videoChunks);

        if (image !== null) {
            await page.waitForFunction(
                (source) =>
                    [...document.images].some(
                        (element) =>
                            element.src === source && element.naturalWidth > 0,
                    ),
                image.url,
                { timeout: 30_000 },
            );
            run.check('the image renders from its media URL', true, image.url);
        }

        if (clip !== null) {
            const facts = await page.evaluate(
                async ({ url, size }) => {
                    const response = await fetch(url);
                    const bytes = new Uint8Array(await response.arrayBuffer());

                    return {
                        status: response.status,
                        length: bytes.length,
                        head: String.fromCharCode(...bytes.slice(4, 12)),
                        size,
                    };
                },
                { url: clip.url, size: clip.size },
            );

            run.check(
                'the stored video is the uploaded bytes on the media host',
                facts.status === 200 &&
                    facts.length === clip.size &&
                    facts.head === 'ftypmp42',
                facts,
            );
            run.note('videoFacts', facts);
        }

        await run.step('after-uploads');
    },

    /** features/storage-bar.md */
    'storage-bar': async (run) => {
        const { page } = run;

        await signIn(run);
        await quietFirstVisits(run);
        await page.reload();
        await page.locator(tid('@sidebar-workspace-menu')).waitFor();

        await page.locator(tid('@sidebar-workspace-menu')).click();
        const usage = page.locator(tid('@sidebar-menu-storage-usage'));
        await usage.waitFor();
        await run.step('account-menu-storage');

        const storage = await page.evaluate(() => {
            return window.history.state?.page?.props?.storage ?? null;
        });
        const shown = (await usage.textContent())?.trim() ?? null;
        run.check(
            'the storage row shows the usage against the quota',
            storage !== null &&
                shown !== null &&
                shown.includes(storage.used_label) &&
                shown.includes(storage.limit_label),
            { shown, storage },
        );
        run.check(
            'the storage row has a progress bar',
            (await page.locator(tid('@sidebar-menu-storage-progress')).count()) ===
                1,
            await page.locator(tid('@sidebar-menu-storage-progress')).count(),
        );
        run.note('storage', storage);

        await page.locator(tid('@sidebar-menu-storage')).click();
        await page.waitForURL((url) => url.pathname === '/settings/preferences');
        // SettingsSelect puts the test id on its trigger, not on the row.
        const videoPolicy = page.locator('#video-policy');
        await videoPolicy.waitFor();
        await run.step('preferences-video-policy');

        run.check(
            'the storage row opens the Video policy',
            new URL(page.url()).hash === '#video-policy' &&
                (await videoPolicy.isVisible()) &&
                (await page
                    .locator(tid('@preferences-video-policy-trigger'))
                    .isVisible()),
            {
                hash: new URL(page.url()).hash,
                visible: await videoPolicy.isVisible(),
            },
        );
    },

    /** features/language-switch.md */
    'language-switch': async (run) => {
        const { page } = run;

        await signIn(run);
        await quietFirstVisits(run);
        await page.reload();
        await page.waitForURL((url) => url.pathname.startsWith('/schedule'));
        await page.locator(tid('@sidebar-workspace-menu')).waitFor();

        // A full page load would clear this flag.
        await page.evaluate(() => {
            (window as unknown as { __notReloaded?: boolean }).__notReloaded =
                true;
        });

        await page.locator(tid('@sidebar-workspace-menu')).click();
        const trigger = page.locator(tid('@sidebar-language-trigger'));
        await trigger.waitFor();

        // A Radix sub-trigger opens on a pointer move, not on a click alone.
        await trigger.dispatchEvent('pointermove');
        await trigger.click();
        await page.locator(tid('@sidebar-language-ja')).click();

        // The sidebar's Publish link is "公開" once the locale is Japanese.
        const published = page.getByRole('link', {
            name: '公開',
            exact: true,
        });
        await published.waitFor();
        await run.step('sidebar-in-japanese');

        const notReloaded = await page.evaluate(() => {
            return (
                (window as unknown as { __notReloaded?: boolean })
                    .__notReloaded === true
            );
        });
        run.check(
            'the page translates without a full reload',
            notReloaded,
            notReloaded,
        );
        run.check(
            'the sidebar reads in Japanese',
            (await published.count()) === 1,
            await published.count(),
        );

        const locale = await page.evaluate(() => {
            return window.history.state?.page?.props?.locale ?? null;
        });
        run.check('the shared locale is ja', locale === 'ja', locale);
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
