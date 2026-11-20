<script setup lang="ts">
import { computed } from 'vue';

import { LANDING_SCREENSHOTS } from '@/lib/landingScreenshots';
import type { LandingScreenshotKey } from '@/types/landing';

const props = withDefaults(
    defineProps<{
        shot: LandingScreenshotKey;
        /** The hero's screen loads at once; every other one when it nears the viewport. */
        eager?: boolean;
    }>(),
    { eager: false },
);

/** Phones get their own capture instead of the desktop one shrunk. */
const PHONE_MEDIA = '(max-width: 639px)';

const screenshot = computed(() => LANDING_SCREENSHOTS[props.shot]);

const loading = computed<'eager' | 'lazy'>(() => {
    if (props.eager) {
        return 'eager';
    }

    return 'lazy';
});

const fetchPriority = computed<'high' | 'auto'>(() => {
    if (props.eager) {
        return 'high';
    }

    return 'auto';
});
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-border bg-card shadow-md"
        :data-testid="`public-screenshot-${shot}`"
    >
        <picture>
            <source
                :media="PHONE_MEDIA"
                :srcset="screenshot.phone.src"
                :width="screenshot.phone.width"
                :height="screenshot.phone.height"
            />
            <img
                :src="screenshot.desktop.src"
                :width="screenshot.desktop.width"
                :height="screenshot.desktop.height"
                :alt="$t(`rxdesk.public.screenshots.${shot}`)"
                :loading="loading"
                :fetchpriority="fetchPriority"
                decoding="async"
                class="block h-auto w-full"
            />
        </picture>
    </div>
</template>
