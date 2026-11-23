<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Progress } from '@/components/ui/progress';
import date from '@/date';
import { filled } from '@/lib/helpers';
import { formatNumber } from '@/lib/utils';
import { index as billingIndex } from '@/routes/app/billing';
import type { AiCredits, SharedData } from '@/types';

type Translate = (key: string, replace?: Record<string, string>) => string;

const LOW_PERCENT = 90;
const PROGRESS_CLASS = 'h-1.5';
const LOW_PROGRESS_CLASS = `${PROGRESS_CLASS} bg-destructive/20 [&>[data-slot=progress-indicator]]:bg-destructive`;

const page = usePage<SharedData>();

const credits = computed((): AiCredits | null => {
    const shared = page.props.aiCredits;

    if (filled(shared)) {
        return shared;
    }

    return null;
});

const usageLine = ($t: Translate): string => {
    const value = credits.value;

    if (value === null) {
        return '';
    }

    return $t('rxdesk.ai_credits.usage_line', {
        used: formatNumber(value.used),
        total: formatNumber(value.total),
        date: date.formatDate(value.renews_at),
    });
};

const billingUrl = computed<string>(() => `${billingIndex.url()}#usage`);

const progressClass = computed<string>(() => {
    const value = credits.value;

    if (value === null) {
        return PROGRESS_CLASS;
    }

    if (value.percent >= LOW_PERCENT) {
        return LOW_PROGRESS_CLASS;
    }

    return PROGRESS_CLASS;
});
</script>

<template>
    <DropdownMenuItem v-if="credits" :as-child="true" class="h-auto p-0">
        <Link
            :href="billingUrl"
            class="flex w-full cursor-pointer flex-col items-stretch gap-1.5 rounded-md px-2 py-1.5"
            data-testid="sidebar-menu-ai-credits"
        >
            <span class="flex items-baseline justify-between gap-2 text-xs">
                <span class="font-medium text-foreground">{{
                    $t('rxdesk.ai_credits.label')
                }}</span>
                <span
                    class="truncate text-muted-foreground"
                    data-testid="sidebar-menu-ai-credits-usage"
                >
                    {{ usageLine($t) }}
                </span>
            </span>
            <Progress
                :model-value="credits.percent"
                :class="progressClass"
                data-testid="sidebar-menu-ai-credits-progress"
            />
        </Link>
    </DropdownMenuItem>
</template>
