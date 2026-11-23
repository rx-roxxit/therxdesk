<script setup lang="ts">
import { IconAlertTriangle } from '@tabler/icons-vue';
import { computed } from 'vue';

import { getPlatformLabel } from '@/composables/usePlatformLogo';

const props = defineProps<{
    networks: string[];
}>();

const networksShown = computed(() => [...new Set(props.networks)]);
</script>

<template>
    <div
        v-if="networksShown.length"
        class="flex flex-col gap-1.5 rounded-md bg-warning/15 px-3 py-2 text-sm text-foreground"
        data-testid="platform-unavailable-banner"
    >
        <p
            v-for="network in networksShown"
            :key="network"
            class="flex items-start gap-2"
            :data-testid="`platform-unavailable-${network}`"
        >
            <IconAlertTriangle
                class="mt-0.5 size-4 shrink-0 text-warning"
                aria-hidden="true"
            />
            <span>{{
                $t('rxdesk.availability.banner', {
                    network: getPlatformLabel(network),
                })
            }}</span>
        </p>
    </div>
</template>
