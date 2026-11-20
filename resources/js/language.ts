import { getActiveLanguage, loadLanguageAsync } from 'laravel-vue-i18n';
import { ref } from 'vue';

import dayjs from '@/dayjs';
import { filled } from '@/lib/helpers';
import type { Auth, Language } from '@/types';

interface LocaleProps {
    locale?: string;
    auth?: Auth;
    languages?: Language[];
}

const chosen = ref<string | null>(null);

/**
 * The RX Desk: where a logged-out visitor's pick is kept for the tab. Moving
 * between the public pages and the auth screens loads the page in full (see
 * `HandleInertiaRequests::version()`), which would drop a pick held in memory
 * only. A guest has no user id, so this cannot live in the per-user browser
 * storage; it is the one sessionStorage key, removed once the visitor signs
 * in.
 */
const GUEST_LOCALE_KEY = 'trypost.guest-locale';

const storedGuestLocale = (): string | null => {
    try {
        return window.sessionStorage.getItem(GUEST_LOCALE_KEY);
    } catch {
        return null;
    }
};

const storeGuestLocale = (locale: string | null): void => {
    try {
        if (!filled(locale)) {
            window.sessionStorage.removeItem(GUEST_LOCALE_KEY);

            return;
        }

        window.sessionStorage.setItem(GUEST_LOCALE_KEY, locale);
    } catch {
        // Storage refused: the pick lasts until the next full page load.
    }
};

/**
 * The locale the page is currently rendered in. `dayjs.locale()` is global and
 * not reactive, so anything formatting a date has to depend on this instead —
 * otherwise a cached computed keeps the previous language's month and day names.
 */
export const activeLocale = ref('en');

export const textDirection = ref<'ltr' | 'rtl'>(
    document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr',
);

export const guestLocale = (): string | null => chosen.value;

/**
 * Align everything outside the translation strings themselves.
 */
const align = (locale: string, direction?: string): void => {
    dayjs.locale(locale.toLowerCase());
    activeLocale.value = locale;
    document.documentElement.lang = locale;

    if (direction) {
        document.documentElement.dir = direction;
        textDirection.value = direction === 'rtl' ? 'rtl' : 'ltr';
    }
};

/**
 * Only Arabic is right to left, but the rule lives in the Locale enum and
 * reaches the client in the shared languages prop.
 */
const directionOf = (
    locale: string,
    languages?: Language[],
): string | undefined =>
    languages?.find((language) => language.code === locale)?.dir;

/**
 * The locale the app starts in. The i18n plugin loads the strings itself from
 * the value returned here, so this only aligns dayjs and the document.
 */
export const bootLocale = (props: LocaleProps): string => {
    const kept = keptGuestLocale(props);

    if (filled(kept)) {
        chosen.value = kept;
        align(kept, directionOf(kept, props.languages));

        return kept;
    }

    const locale = props.locale ?? 'en';

    align(locale);

    return locale;
};

/**
 * The language a logged-out visitor picked earlier in this tab, while it is
 * still one the app offers. A signed-in page forgets it: the account's
 * language decides from then on.
 */
const keptGuestLocale = (props: LocaleProps): string | null => {
    if (filled(props.auth?.user)) {
        storeGuestLocale(null);

        return null;
    }

    const stored = storedGuestLocale();

    if (!filled(stored)) {
        return null;
    }

    if (!filled(directionOf(stored, props.languages))) {
        return null;
    }

    return stored;
};

/**
 * Record a logged-out visitor's pick and apply it. It has to be remembered: the
 * server renders every guest page in the default, so nothing else would keep the
 * choice across an Inertia visit between the auth screens.
 */
export const chooseGuestLocale = (language: Language): void => {
    chosen.value = language.code;
    storeGuestLocale(language.code);

    void loadLanguageAsync(language.code);
    align(language.code, language.dir);
};

/**
 * Re-align after an Inertia visit. The locale is read once at boot but changes
 * mid-session — logging in swaps the guest default for the account's language
 * over a visit that never re-runs the app setup.
 */
export const syncLocale = (props: LocaleProps): void => {
    const authenticated = Boolean(props.auth?.user);

    if (authenticated) {
        chosen.value = null;
        storeGuestLocale(null);
    }

    const next = authenticated ? props.locale : (chosen.value ?? props.locale);

    if (!next || next === getActiveLanguage()) {
        return;
    }

    void loadLanguageAsync(next);
    align(next, directionOf(next, props.languages));
};

export const i18nConfig = (lang: string) => ({
    lang,
    resolve: async (locale: string) => {
        const langs = import.meta.glob<{ default: Record<string, string> }>(
            '../../lang/*.json',
        );

        return await langs[`../../lang/php_${locale}.json`]();
    },
});
