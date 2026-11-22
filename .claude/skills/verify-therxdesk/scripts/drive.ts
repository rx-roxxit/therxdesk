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
const quietFirstVisits = async (
    run: Run,
    userId: string = run.fixture.userId,
): Promise<void> => {
    await run.page.evaluate((id) => {
        const seen = { status: 'skipped', step: 1 };

        localStorage.setItem(
            `trypost:${id}`,
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
    }, userId);
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
    'blocked-country',
    'selling-mode',
    'welcome-plan',
    'welcome-checkout',
    'billing',
    'payment-retry',
    'past-due',
    'lapsed',
    'media-on-r2',
];

/** The test card Stripe's sandbox always accepts, unless a path needs another. */
const TEST_CARD = process.env.VERIFY_TEST_CARD ?? '4242424242424242';

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

/** Mailpit, where the dev stack sends every email. */
const MAILPIT_URL = process.env.VERIFY_MAILPIT_URL ?? 'http://localhost:8025';

type MailpitMessage = {
    ID: string;
    Date?: string;
    To?: Array<{ Address: string }>;
};

/**
 * Ticks the Terms of Service consent checkbox Stripe Checkout requires. The
 * hosted page draws it differently from release to release and may keep it in
 * a frame, so it is found at runtime; `false` when the session does not ask.
 */
const tickTermsConsent = async (page: Page): Promise<boolean> => {
    const frames = [
        page.mainFrame(),
        ...page.frames().filter((frame) => frame !== page.mainFrame()),
    ];

    for (const frame of frames) {
        const boxes = frame.getByRole('checkbox', {
            name: /terms of service|terms and conditions|i agree/i,
        });
        const box = boxes.first();

        if ((await boxes.count()) === 0 || !(await box.isVisible())) {
            continue;
        }

        if ((await box.getAttribute('aria-checked')) === 'true') {
            return true;
        }

        // Stripe draws a controlled checkbox: the native state does not flip,
        // so click its label and read `aria-checked` instead of `check()`.
        const id = await box.getAttribute('id');
        const label = id === null ? null : frame.locator(`label[for="${id}"]`);

        if (label !== null && (await label.count()) > 0) {
            await label.first().click();
        } else {
            await box.click({ force: true });
        }

        for (let attempt = 0; attempt < 20; attempt++) {
            if ((await box.getAttribute('aria-checked')) === 'true') {
                return true;
            }

            await page.waitForTimeout(100);
        }

        await box.focus();
        await page.keyboard.press('Space');

        for (let attempt = 0; attempt < 20; attempt++) {
            if ((await box.getAttribute('aria-checked')) === 'true') {
                return true;
            }

            await page.waitForTimeout(100);
        }

        return false;
    }

    return false;
};

/**
 * Every Mailpit message to that address, newest first, read through its HTTP
 * API (the same inbox mail.sh prints).
 */
const mailsFor = async (email: string): Promise<MailpitMessage[]> => {
    const response = await fetch(`${MAILPIT_URL}/api/v1/messages?limit=200`);

    if (!response.ok) {
        throw new Error(`Mailpit answered ${response.status}`);
    }

    const body = (await response.json()) as { messages?: MailpitMessage[] };

    return (body.messages ?? [])
        .filter((message) =>
            (message.To ?? []).some((to) => to.Address === email),
        )
        .sort((a, b) => (b.Date ?? '').localeCompare(a.Date ?? ''));
};

/**
 * Waits for a Mailpit message to that address whose id is not `notId`, so a
 * resend is proven by a new email and not by the one signup already sent.
 */
const waitForMail = async (
    email: string,
    notId: string | null,
): Promise<MailpitMessage | null> => {
    for (let attempt = 0; attempt < 30; attempt++) {
        const message = (await mailsFor(email)).find(
            (candidate) => candidate.ID !== notId,
        );

        if (message !== undefined) {
            return message;
        }

        await new Promise((resolve) => setTimeout(resolve, 1_000));
    }

    return null;
};

/** The verification link inside a Mailpit message, or null. */
const verificationLink = async (
    message: MailpitMessage,
): Promise<string | null> => {
    const response = await fetch(`${MAILPIT_URL}/api/v1/message/${message.ID}`);

    if (!response.ok) {
        return null;
    }

    const detail = (await response.json()) as {
        Text?: string;
        HTML?: string;
    };
    const source = `${detail.Text ?? ''}\n${detail.HTML ?? ''}`.replace(
        /&amp;/g,
        '&',
    );
    const match = source.match(
        /https?:\/\/[^"'<>\\\s]+\/verify-email\/[^"'<>\\\s]+/,
    );

    return match?.[0] ?? null;
};

/**
 * The Stripe Elements frame that holds the card form, once Checkout has drawn
 * it. Several iframes load, so the form is the one with the number field.
 */
const waitForStripeCheckoutForm = async (page: Page): Promise<Frame> => {
    for (let attempt = 0; attempt < 60; attempt++) {
        for (const frame of page.frames()) {
            if ((await frame.locator('#payment-numberInput').count()) > 0) {
                return frame;
            }
        }

        await page.waitForTimeout(1_000);
    }

    throw new Error('the Stripe card form never appeared');
};

/**
 * The frame that holds the Checkout form around the card element (billing
 * address, consent), which Stripe keeps separate from the card iframe.
 */
const waitForStripeCheckoutFrame = async (
    page: Page,
): Promise<Frame | null> => {
    for (let attempt = 0; attempt < 30; attempt++) {
        for (const frame of page.frames()) {
            if (
                (await frame.locator('#billingAddress-nameInput').count()) >
                    0 ||
                (await frame
                    .locator('input[name="termsOfServiceConsent"]')
                    .count()) > 0
            ) {
                return frame;
            }
        }

        await page.waitForTimeout(1_000);
    }

    return null;
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

    const form = await waitForStripeCheckoutForm(page);

    await form.locator('#payment-numberInput').waitFor();
    await page.waitForTimeout(1_000);
    await tickTermsConsent(page);
    await run.step('stripe-checkout');

    await form.locator('#payment-numberInput').fill(TEST_CARD);
    await form.locator('#payment-expiryInput').fill(testCardExpiry());
    await form.locator('#payment-cvcInput').fill('123');

    // The card form carries the name unless the Checkout collects a billing
    // address, in which case the main page does.
    const cardName = form.locator('#payment-nameInput');

    if (await cardName.isVisible()) {
        await cardName.fill('Verify Agent');
    }

    // A required billing address asks for the buyer and the address; Stripe
    // keeps those fields in the Checkout frame, not the card iframe. Fill what
    // is on screen. The country follows the IP (Thailand here).
    const checkoutFrame = await waitForStripeCheckoutFrame(page);
    const billing = {
        '#billingAddress-nameInput': 'Verify Agent',
        '#billingAddress-addressLine1Input': '1 Verify Road',
        '#billingAddress-localityInput': 'Bangkok',
        '#billingAddress-postalCodeInput': '10110',
    };

    for (const [selector, value] of Object.entries(billing)) {
        const field = checkoutFrame?.locator(selector);

        if (field !== undefined && (await field.isVisible())) {
            await field.fill(value);
        }
    }

    // Select Thailand explicitly: the completed-checkout webhook cancels a
    // subscription billed to an EU/UK country, so a paid journey must never
    // rely on the IP deciding the billing country.
    const country = checkoutFrame?.locator('#billingAddress-countryInput');
    const countryIsSelect =
        country !== undefined &&
        (await country.count()) > 0 &&
        (await country.evaluate((field) => field.tagName)) === 'SELECT';
    let billingCountry = '';

    if (country !== undefined && countryIsSelect) {
        await country.selectOption('TH');
        billingCountry = await country.inputValue();
    } else {
        const combobox = checkoutFrame?.getByRole('combobox', {
            name: /country/i,
        });

        if (combobox !== undefined && (await combobox.count()) > 0) {
            await combobox.click();
            await checkoutFrame
                ?.getByRole('option', { name: /Thailand/i })
                .first()
                .click();
            billingCountry = (await combobox.textContent()) ?? '';
        }
    }

    run.check(
        'Stripe Checkout bills to Thailand (an allowed country)',
        billingCountry.trim() === 'TH' || billingCountry.includes('Thailand'),
        billingCountry,
    );

    // A US card form keeps its ZIP inside the card element.
    const cardPostalCode = form.locator('#payment-postalCodeInput');

    if (await cardPostalCode.isVisible()) {
        await cardPostalCode.fill('10001');
    }

    // Stripe Link offers to save the card; leave it off so no Link account is made.
    const saveWithLink = form.locator('#payment-linkOptInInput');

    if ((await saveWithLink.isVisible()) && (await saveWithLink.isChecked())) {
        await saveWithLink.uncheck();
    }

    await run.step('stripe-checkout-filled');

    const submit = checkoutFrame?.locator(
        '[data-testid="payment-form-submit-button"]',
    );

    if (submit !== undefined && (await submit.count()) > 0) {
        await submit.click();

        return;
    }

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
        await page.locator(tid('@register-country')).selectOption('TH');
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

    /** features/registration.md (the EU/UK block) */
    'blocked-country': async (run) => {
        const { page } = run;

        const email = guestEmail();
        run.note('blockedSignupEmail', email);

        await page.goto(`${run.baseUrl}/register`);
        await page.locator(tid('@register-user-button')).waitFor();
        await page.waitForLoadState('networkidle');
        await run.step('blocked-country-form');

        await page.locator(tid('@register-name')).fill('Verify Blocked');
        await page.locator(tid('@register-email')).fill(email);
        await page
            .locator(tid('@register-password'))
            .fill('Tr7#pass-Word-2026');
        await page.locator(tid('@register-country')).selectOption('DE');
        await run.step('blocked-country-filled');

        await page.locator(tid('@register-user-button')).click();

        const notice = page.getByText('Not available in the EU/UK yet', {
            exact: false,
        });

        await notice
            .first()
            .waitFor({ timeout: 15_000 })
            .catch(() => null);

        await page.waitForLoadState('networkidle');
        await run.step('blocked-country-refused');

        const path = new URL(page.url()).pathname;
        run.check(
            'the blocked signup stays on the register page',
            path === '/register',
            path,
        );
        run.check(
            'the blocked notice is shown',
            (await notice.count()) > 0,
            await notice.count(),
        );

        const signedIn = await signedInEmail(page);
        run.check(
            'the blocked signup is not signed in',
            signedIn === null,
            signedIn,
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

    /**
     * features/welcome-checkout.md
     *
     * The plan step alone: the `/register` link's plan and interval, the four
     * cards, the Monthly/Yearly switch, the verify-email dialog and a Checkout
     * with Terms consent and no promotion codes, then Processing and Schedule.
     */
    'welcome-plan': async (run) => {
        const { page } = run;
        const email = guestEmail();
        run.note('signupEmail', email);

        await page.goto(`${run.baseUrl}/register?plan=plus&interval=yearly`);
        await page.locator(tid('@register-user-button')).waitFor();
        await page.locator(tid('@register-name')).fill('Verify Plan');
        await page.locator(tid('@register-email')).fill(email);
        await page
            .locator(tid('@register-password'))
            .fill('Tr7#pass-Word-2026');
        await page.locator(tid('@register-country')).selectOption('TH');
        await page.locator(tid('@register-user-button')).click();
        await page.waitForURL((url) => url.pathname.startsWith('/welcome'), {
            timeout: 30_000,
        });

        await page.locator(tid('@welcome-persona-continue')).waitFor();
        await page.locator(tid('@welcome-persona-creator')).click();
        await page.locator(tid('@welcome-persona-continue')).click();
        await page.locator(tid('@welcome-goals-continue')).waitFor();
        await page.locator(tid('@welcome-goal-save_time')).click();
        await page.locator(tid('@welcome-goals-continue')).click();
        await page.locator(tid('@welcome-software-continue')).waitFor();
        await page.locator(tid('@welcome-software-none')).click();
        await page.locator(tid('@welcome-software-continue')).click();

        await page.locator(tid('@plan-card-mini')).waitFor();
        await page.waitForLoadState('networkidle');
        await run.step('welcome-plan');

        const cards = await page
            .locator('[data-testid^="plan-card-"]')
            .evaluateAll((elements) =>
                elements.map(
                    (element) => (element as HTMLElement).dataset.testid,
                ),
            );
        run.check(
            'the plan step shows four cards in Catalog order',
            JSON.stringify(cards) ===
                JSON.stringify([
                    'plan-card-mini',
                    'plan-card-standard',
                    'plan-card-plus',
                    'plan-card-max',
                ]),
            cards,
        );

        const yearlyPressed = await page
            .locator(tid('@plan-interval-yearly'))
            .getAttribute('aria-pressed');
        run.check(
            'the yearly interval from the register link is preselected',
            yearlyPressed === 'true',
            yearlyPressed,
        );

        const plusSelected = await page
            .locator(tid('@plan-card-plus'))
            .getAttribute('data-selected');
        run.check(
            'the Plus plan from the register link is preselected',
            plusSelected === 'true',
            plusSelected,
        );

        const yearlyPlus = await page
            .locator(tid('@plan-price-plus'))
            .innerText();
        const yearlyUnit = await page
            .locator(tid('@plan-price-unit-plus'))
            .innerText();
        run.check(
            'yearly shows Plus at $299.00 a year',
            yearlyPlus.includes('$299.00') && yearlyUnit.includes('/year'),
            { yearlyPlus, yearlyUnit },
        );

        await page.locator(tid('@plan-interval-monthly')).click();
        await page.waitForFunction(() => {
            return document
                .querySelector('[data-testid="plan-price-plus"]')
                ?.textContent?.includes('$29.90');
        });
        const monthlyPlus = await page
            .locator(tid('@plan-price-plus'))
            .innerText();
        run.check(
            'monthly shows Plus at $29.90 a month',
            monthlyPlus.includes('$29.90'),
            monthlyPlus,
        );

        await page.locator(tid('@plan-interval-yearly')).click();
        await page.waitForFunction(() => {
            return document
                .querySelector('[data-testid="plan-price-plus"]')
                ?.textContent?.includes('$299.00');
        });
        await run.step('welcome-plan-yearly');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.locator(tid('@plan-card-mini')).waitFor();
        await page.evaluate(
            () =>
                new Promise((resolve) =>
                    requestAnimationFrame(() => requestAnimationFrame(resolve)),
                ),
        );
        const clipped = await page
            .locator('[data-testid^="plan-select-"]')
            .evaluateAll((elements) =>
                elements
                    .filter(
                        (element) => element.scrollWidth > element.clientWidth,
                    )
                    .map((element) => (element as HTMLElement).dataset.testid),
            );
        run.check(
            'every plan button fits its slot at 390px',
            clipped.length === 0,
            clipped,
        );
        await run.step('welcome-plan-phone');
        await page.setViewportSize({ width: 1440, height: 900 });

        // The new Owner's email is unverified: the first press opens the dialog.
        await page.locator(tid('@plan-select-plus')).click();
        await page.locator(tid('@verify-email-dialog')).waitFor();
        await run.step('verify-dialog');

        // Signup already sent one verification email; the resend must add another.
        const signupMail = await waitForMail(email, null);
        run.check(
            'signup sends a verification email',
            signupMail !== null,
            signupMail?.ID ?? null,
        );

        await page.locator(tid('@verify-email-resend')).click();

        const resentMail = await waitForMail(email, signupMail?.ID ?? null);
        run.check(
            'the resend delivers a new verification email',
            resentMail !== null,
            resentMail?.ID ?? null,
        );

        const link =
            resentMail === null ? null : await verificationLink(resentMail);
        run.check(
            'the verification email carries a working link',
            typeof link === 'string',
            link,
        );

        if (link === null) {
            throw new Error('no verification link in Mailpit');
        }

        await page.goto(link);
        await page.waitForURL((url) => url.pathname === '/welcome/plan', {
            timeout: 30_000,
        });
        await page.locator(tid('@plan-card-mini')).waitFor();
        await run.step('after-verification');

        await page.locator(tid('@plan-select-plus')).click();
        await page.waitForURL((url) => url.hostname === 'checkout.stripe.com', {
            timeout: 60_000,
        });
        await waitForStripeCheckoutForm(page);
        await page.waitForTimeout(1_500);

        const hasTerms = await tickTermsConsent(page);
        run.check('Stripe Checkout asks for Terms consent', hasTerms, hasTerms);

        const promotionField = page.locator(
            '#promotion-code, input[name="promotion_code"]',
        );
        const promotionLink = page.getByText('Add promotion code', {
            exact: false,
        });
        const promotionCount =
            (await promotionField.count()) + (await promotionLink.count());
        run.check(
            'Stripe Checkout offers no promotion code field',
            promotionCount === 0,
            {
                fields: await promotionField.count(),
                links: await promotionLink.count(),
            },
        );
        await run.step('stripe-checkout-terms');

        await payOnStripeCheckout(run);

        await page.waitForURL((url) => url.pathname === '/billing/processing', {
            timeout: 90_000,
        });
        await run.step('processing');
        await page.waitForURL((url) => url.pathname.startsWith('/schedule'), {
            timeout: 90_000,
        });
        await page.waitForLoadState('networkidle');
        const guestUserId = await page.evaluate(() => {
            return (
                window.history.state?.page?.props?.auth?.user?.id ?? undefined
            );
        });
        await quietFirstVisits(run, guestUserId);
        await page.reload();
        await page.waitForLoadState('networkidle');
        await page.locator(tid('@schedule-view-list')).waitFor();
        await run.step('after-checkout');

        const auth = await page.evaluate(() => {
            const props = window.history.state?.page?.props?.auth;

            return {
                accountId: props?.account?.id ?? null,
                hasActiveSubscription: props?.hasActiveSubscription ?? null,
                plan: props?.plan?.name ?? null,
            };
        });
        run.note('accountId', auth.accountId);
        run.check(
            'the Account has an active subscription after the webhook',
            auth.hasActiveSubscription === true,
            auth,
        );
        run.check('the Account is on Plus', auth.plan === 'Plus', auth.plan);
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
        run.check(
            'the plan step shows the four Plans',
            (await page.locator('[data-testid^="plan-card-"]').count()) === 4,
            await page.locator('[data-testid^="plan-card-"]').count(),
        );
        await run.step('welcome-plan');

        const price = await page.locator(tid('@plan-price-mini')).innerText();
        run.check(
            'the Plan step shows Mini at $11.90',
            price.includes('$11.90'),
            price,
        );

        await page.locator(tid('@plan-select-mini')).click();
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
        // After welcome-checkout the first checkout is on the Catalog trial,
        // so the badge says "Free trial"; a run after the trial says "Active".
        run.check(
            'the current plan is Mini, $11.90/month, on a Free trial or Active',
            currentPlan.name.includes('Mini') &&
                currentPlan.price.includes('$11.90') &&
                (currentPlan.badge.includes('Free trial') ||
                    currentPlan.badge.includes('Active')),
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
     * features/trial-and-payment.md (path 2: a Trial's first charge failed)
     *
     * The Account never paid and its Trial's first charge was declined, so it
     * has no access: the plan step is the retry state, not the four cards.
     */
    'payment-retry': async (run) => {
        const { page } = run;

        await signIn(run);
        await page.waitForURL((url) => url.pathname === '/welcome/plan', {
            timeout: 30_000,
        });
        await page.locator(tid('@plan-payment-retry')).waitFor();
        await page.waitForLoadState('networkidle');
        await run.step('payment-retry');

        const title = await page.locator(tid('@welcome-title')).innerText();
        run.check(
            "the retry step says the first payment didn't go through",
            title.includes("Your first payment didn't go through"),
            title,
        );

        const cards = await page.locator('[data-testid^="plan-card-"]').count();
        run.check('the retry step shows no plan cards', cards === 0, cards);

        await page.locator(tid('@plan-payment-retry-update-card')).click();
        await page.waitForURL((url) => url.hostname === 'billing.stripe.com', {
            timeout: 60_000,
        });
        await run.step('stripe-portal');

        const host = new URL(page.url()).hostname;
        run.check(
            'Update card opens the Customer Portal',
            host === 'billing.stripe.com',
            host,
        );
    },

    /**
     * features/trial-and-payment.md (path 3: a renewal failed after a paid one)
     *
     * The Account paid at least once, so a past_due subscription keeps its
     * access: the Schedule opens with the sidebar banner, and billing still
     * shows the Plan with a Past due badge.
     */
    'past-due': async (run) => {
        const { page } = run;

        await signIn(run);
        await page.waitForURL((url) => url.pathname.startsWith('/schedule'), {
            timeout: 30_000,
        });
        await quietFirstVisits(run);
        await page.reload();
        await page.waitForURL((url) => url.pathname.startsWith('/schedule'), {
            timeout: 30_000,
        });
        await page.locator(tid('@sidebar-past-due')).waitFor();
        await run.step('past-due-schedule');

        const notice = await page.locator(tid('@sidebar-past-due')).innerText();
        run.check(
            'the sidebar says the payment is past due',
            notice.trim().length > 0,
            notice,
        );

        await page.locator(tid('@sidebar-workspace-menu')).click();
        await page.locator(tid('@sidebar-menu-billing')).click();
        await page.waitForURL(
            (url) => url.pathname === '/settings/account/billing',
        );
        await page.locator(tid('@billing-current-plan-badge')).waitFor();
        await page.waitForLoadState('networkidle');
        await run.step('billing-past-due');

        const badge = await page
            .locator(tid('@billing-current-plan-badge'))
            .innerText();
        run.check(
            'billing shows the subscription as Past due',
            badge.includes('Past due'),
            badge,
        );

        const planName = await page.evaluate(() => {
            return window.history.state?.page?.props?.auth?.plan?.name ?? null;
        });
        const shownName = await page
            .locator(tid('@billing-current-plan-name'))
            .innerText();
        run.check(
            'billing still shows the Plan',
            typeof planName === 'string' &&
                planName.length > 0 &&
                shownName.includes(planName),
            { planName, shownName },
        );
    },

    /**
     * features/trial-and-payment.md (path 4: Smart Retries ran out)
     *
     * A lapsed Account has no access: the plan step is back, without the retry
     * state, and a card's button says Subscribe because the Trial is not
     * offered again.
     */
    lapsed: async (run) => {
        const { page } = run;

        await signIn(run);
        await page.waitForURL((url) => url.pathname === '/welcome/plan', {
            timeout: 30_000,
        });
        await page.locator(tid('@plan-card-mini')).waitFor();
        await page.waitForLoadState('networkidle');
        await run.step('lapsed-plan');

        const retry = await page.locator(tid('@plan-payment-retry')).count();
        run.check('a lapsed Account has no retry step', retry === 0, retry);

        const button = await page.locator(tid('@plan-select-mini')).innerText();
        run.check(
            'the lapsed Account is offered a subscription, not a Trial',
            button.includes('Subscribe'),
            button,
        );

        const offer = await page.locator(tid('@welcome-plan-offer')).count();
        run.check('the plan step shows no Offer banner', offer === 0, offer);
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
            (await page
                .locator(tid('@sidebar-menu-storage-progress'))
                .count()) === 1,
            await page.locator(tid('@sidebar-menu-storage-progress')).count(),
        );
        run.note('storage', storage);

        await page.locator(tid('@sidebar-menu-storage')).click();
        await page.waitForURL(
            (url) => url.pathname === '/settings/preferences',
        );
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
