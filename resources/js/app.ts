import '@/echo';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import { ConfigProvider } from 'reka-ui';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

import { initializeDataLayer } from '@/datalayer';
import { bootLocale, i18nConfig, syncLocale, textDirection } from '@/language';
import { syncContentTypeMediaRules } from '@/lib/contentTypeMediaRules';
import { trackInAppPushes } from '@/lib/inAppHistory';
import { createPageTracking } from '@/lib/tracking';
import {
    capturePageview,
    initializePostHog,
    syncPostHogContext,
} from '@/posthog';
import { syncPreferences } from '@/preferences';
import type { Auth } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || 'TryPost.it';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const locale = bootLocale(props.initialPage.props);
        syncPreferences(props.initialPage.props);

        // PostHog identify + dual-group context + pageview on the first page
        // and on every Inertia navigation below, so the account group counts
        // stay reactive and workspace switches re-attach the right workspace
        // group. A public page (the landing, the legal pages) is skipped:
        // it carries no analytics.
        const trackPage = createPageTracking({
            initializeDataLayer: (page) =>
                initializeDataLayer(
                    page.props.auth as Auth | undefined,
                    page.props.flash as
                        | { conversion_event?: string; [key: string]: unknown }
                        | undefined,
                    page.props.applicationUrl as string,
                    page.props.env as string,
                ),
            initializePostHog,
            syncPostHogContext,
            capturePageview,
        });

        trackPage(props.initialPage);
        syncContentTypeMediaRules(props.initialPage);

        // `success`, not `navigate`: switching language answers with `back()`,
        // which keeps the same URL, so Inertia updates the props without
        // reporting a navigation.
        router.on('success', (event) => {
            syncLocale(event.detail.page.props);
            syncPreferences(event.detail.page.props);
        });

        trackInAppPushes();

        router.on('navigate', (event) => {
            trackPage(event.detail.page);
            syncContentTypeMediaRules(event.detail.page);
        });

        let mounted = false;

        const app = createApp({
            render: () =>
                h(ConfigProvider, { dir: textDirection.value }, () =>
                    h(App, props),
                ),
        });

        app.use(i18nVue, {
            ...i18nConfig(locale),
            onLoad: () => {
                if (mounted) {
                    return;
                }

                mounted = true;
                app.mount(el);
            },
        }).use(plugin);
    },
    progress: {
        color: '#4B5563',
    },
});
