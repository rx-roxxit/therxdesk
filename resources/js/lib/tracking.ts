import type { Page } from '@inertiajs/core';

/**
 * The analytics the app bootstrap runs, passed in by `app.ts` so this file
 * stays free of PostHog and GTM (and runs under Node in `PageTrackingTest`).
 */
export interface PageTrackers {
    /** GTM's data layer: app, identity and conversion context. Once. */
    initializeDataLayer: (page: Page) => void;
    /** The PostHog SDK. Once. */
    initializePostHog: () => void;
    /** PostHog identify and the account and workspace groups. Every page. */
    syncPostHogContext: (page: Page) => void;
    /** PostHog's pageview. Every page. */
    capturePageview: () => void;
}

/**
 * Decides tracking page by page. A public page (the landing, the legal
 * pages: the shared `publicPage` flag set by `MarkPublicPage`) carries no
 * analytics: nothing starts, nothing is identified and no pageview is sent.
 * Every other page is tracked, and the first one a tab reaches (at boot, or
 * after arriving on a public page) starts the data layer and PostHog.
 *
 * Returns the function to call with the first page and with every page an
 * Inertia navigation lands on.
 */
export const createPageTracking = (
    trackers: PageTrackers,
): ((page: Page) => void) => {
    let started = false;

    return (page: Page): void => {
        if (page.props.publicPage === true) {
            return;
        }

        if (!started) {
            started = true;
            trackers.initializeDataLayer(page);
            trackers.initializePostHog();
        }

        trackers.syncPostHogContext(page);
        trackers.capturePageview();
    };
};
