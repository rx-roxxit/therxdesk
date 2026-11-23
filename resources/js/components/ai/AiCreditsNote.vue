<script setup lang="ts">
import { computed } from 'vue';

import { useAiCredits } from '@/composables/useAiCredits';
import { transform } from '@/lib/helpers';
import { formatNumber } from '@/lib/utils';

const { credits } = useAiCredits();

const showsLowCredits = computed((): boolean => credits.value?.low === true);

const noteValues = computed((): { remaining: string; total: string } => ({
    remaining: transform(credits.value?.remaining, formatNumber, ''),
    total: transform(credits.value?.total, formatNumber, ''),
}));
</script>

<template>
    <p
        v-if="showsLowCredits"
        class="text-xs text-muted-foreground"
        data-testid="ai-credits-low"
    >
        {{
            $t('rxdesk.ai_credits.low', {
                remaining: noteValues.remaining,
                total: noteValues.total,
            })
        }}
    </p>
</template>
