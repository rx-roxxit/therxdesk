<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

import { filled } from '@/lib/helpers';

interface TurnstileApi {
    render: (
        container: HTMLElement,
        options: Record<string, unknown>,
    ) => string;
    reset: (widgetId: string) => void;
    remove: (widgetId: string) => void;
}

const props = defineProps<{
    siteKey: string;
    language?: string;
}>();

const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_URL =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

const container = ref<HTMLDivElement | null>(null);
const widgetId = ref<string | null>(null);

const api = (): TurnstileApi | undefined =>
    (window as unknown as { turnstile?: TurnstileApi }).turnstile;

/**
 * Cloudflare's codes differ from two of ours, so map those and pass the rest.
 */
const widgetLanguage = (locale: string): string => {
    if (locale === 'pt-BR') {
        return 'pt-br';
    }

    if (locale === 'zh') {
        return 'zh-cn';
    }

    return locale;
};

const loadScript = (): Promise<void> => {
    if (api() !== undefined) {
        return Promise.resolve();
    }

    const existing = document.getElementById(SCRIPT_ID);

    if (existing !== null) {
        return new Promise((resolve) => {
            existing.addEventListener('load', () => resolve(), { once: true });
        });
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');

        script.id = SCRIPT_ID;
        script.src = SCRIPT_URL;
        script.async = true;
        script.defer = true;
        script.addEventListener('load', () => resolve(), { once: true });
        script.addEventListener(
            'error',
            () => reject(new Error('Turnstile failed to load')),
            { once: true },
        );

        document.head.appendChild(script);
    });
};

const render = (): void => {
    const element = container.value;
    const turnstile = api();

    if (element === null || !filled(props.siteKey) || turnstile === undefined) {
        return;
    }

    widgetId.value = turnstile.render(element, {
        sitekey: props.siteKey,
        language: widgetLanguage(props.language ?? 'auto'),
    });
};

/**
 * Turnstile tokens are single use, so a failed submit needs a fresh one.
 */
const reset = (): void => {
    const id = widgetId.value;

    if (id !== null) {
        api()?.reset(id);
    }
};

onMounted(async () => {
    try {
        await loadScript();
        render();
    } catch {
        // Leave the form usable: the server decides whether the check passed.
    }
});

onBeforeUnmount(() => {
    const id = widgetId.value;

    if (id !== null) {
        api()?.remove(id);
    }
});

defineExpose({ reset });
</script>

<template>
    <div ref="container" data-testid="register-turnstile" />
</template>
