/**
 * Plays a visit, one page after another, through `createPageTracking()` of
 * `resources/js/lib/tracking.ts` with trackers that only record their calls,
 * and prints the calls of each visit as JSON, so `PageTrackingTest.php` can
 * check which pages start and capture the analytics.
 * Node strips the types, so the source is loaded as-is.
 */
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const [sourceFile, visitsJson] = process.argv.slice(2);

const { createPageTracking } = await import(
    pathToFileURL(path.resolve(sourceFile)).href
);

type VisitedPage = { url: string; publicPage?: boolean };

const visits = JSON.parse(visitsJson) as VisitedPage[][];

console.log(
    JSON.stringify(
        visits.map((pages) => {
            const calls: string[] = [];

            const trackPage = createPageTracking({
                initializeDataLayer: (page: { url: string }) =>
                    calls.push(`dataLayer ${page.url}`),
                initializePostHog: () => calls.push('posthog'),
                syncPostHogContext: (page: { url: string }) =>
                    calls.push(`identify ${page.url}`),
                capturePageview: () => calls.push('pageview'),
            });

            for (const { url, publicPage } of pages) {
                trackPage({ url, props: { publicPage } });
            }

            return calls;
        }),
    ),
);
