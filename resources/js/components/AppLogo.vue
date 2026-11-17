<script setup lang="ts">
import { computed } from 'vue';

import { RX_MARK } from '@/lib/rxMark';

const props = withDefaults(
    defineProps<{
        /** `full` is the RX mark and "The RX Desk" wordmark, `mark` the mark alone. */
        variant?: 'full' | 'mark';
    }>(),
    { variant: 'full' },
);

const viewBox = `0 0 ${RX_MARK.grid} ${RX_MARK.grid}`;

const showsWordmark = computed(() => props.variant === 'full');
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-[0.32em] font-logo leading-none font-bold tracking-[-0.01em] text-foreground"
        role="img"
        aria-label="The RX Desk"
        data-testid="app-logo"
    >
        <svg
            :viewBox="viewBox"
            class="size-[1.15em] shrink-0"
            aria-hidden="true"
        >
            <rect
                :width="RX_MARK.grid"
                :height="RX_MARK.grid"
                :rx="RX_MARK.radius"
                class="fill-foreground"
            />
            <path
                :d="RX_MARK.letters"
                fill="none"
                class="stroke-background"
                :stroke-width="RX_MARK.strokeWidth"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
        <span v-if="showsWordmark" aria-hidden="true">The RX Desk</span>
    </span>
</template>
