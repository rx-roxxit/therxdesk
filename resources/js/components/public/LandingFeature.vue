<script setup lang="ts">
import { computed } from 'vue';

import LandingScreenshot from '@/components/public/LandingScreenshot.vue';
import type {
    LandingFeatureSection,
    LandingScreenshotKey,
} from '@/types/landing';

const props = withDefaults(
    defineProps<{
        section: LandingFeatureSection;
        /** The keys under `rxdesk.public.<section>.points`, in reading order. */
        points: string[];
        shot: LandingScreenshotKey;
        /** Canvas bands alternate with white ones down the page. */
        canvas?: boolean;
        /** Puts the screen first on wide screens; the copy still reads first. */
        flip?: boolean;
    }>(),
    { canvas: false, flip: false },
);

const titleId = computed(() => `public-${props.section}-title`);

const bandClass = computed(() => {
    if (props.canvas) {
        return 'bg-muted';
    }

    return 'bg-background';
});

const copyClass = computed(() => {
    if (props.flip) {
        return 'lg:order-2';
    }

    return '';
});

const key = (path: string): string => `rxdesk.public.${props.section}.${path}`;
</script>

<template>
    <section
        :class="['border-b border-border py-14 sm:py-16', bandClass]"
        :aria-labelledby="titleId"
        :data-testid="`public-section-${section}`"
    >
        <div
            class="mx-auto grid w-full max-w-6xl grid-cols-1 items-center gap-8 px-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] lg:gap-14"
        >
            <div :class="copyClass">
                <h2
                    :id="titleId"
                    class="font-heading text-2xl leading-tight font-semibold tracking-tight text-balance text-foreground sm:text-3xl"
                >
                    {{ $t(key('title')) }}
                </h2>
                <p class="mt-4 max-w-[60ch] text-base text-muted-foreground">
                    {{ $t(key('body')) }}
                </p>
                <ul
                    class="mt-6 max-w-[60ch] divide-y divide-border border-y border-border"
                >
                    <li
                        v-for="point in points"
                        :key="point"
                        class="py-3 text-sm text-muted-foreground"
                    >
                        <span class="font-medium text-foreground">
                            {{ $t(key(`points.${point}.title`)) }}
                        </span>
                        {{ $t(key(`points.${point}.text`)) }}
                    </li>
                </ul>
            </div>

            <LandingScreenshot :shot="shot" />
        </div>
    </section>
</template>
