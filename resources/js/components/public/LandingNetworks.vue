<script setup lang="ts">
import PlatformLogo from '@/components/PlatformLogo.vue';
import type { LandingNetwork } from '@/types/landing';

defineProps<{
    networks: LandingNetwork[];
}>();
</script>

<template>
    <section
        class="border-b border-border bg-background py-14 sm:py-16"
        aria-labelledby="public-networks-title"
        data-testid="public-networks"
    >
        <div class="mx-auto w-full max-w-6xl px-4 sm:px-6">
            <div class="max-w-2xl">
                <h2
                    id="public-networks-title"
                    class="font-heading text-2xl leading-tight font-semibold tracking-tight text-balance text-foreground sm:text-3xl"
                >
                    {{ $t('rxdesk.public.networks.title') }}
                </h2>
                <p class="mt-3 text-base text-muted-foreground">
                    {{ $t('rxdesk.public.networks.lead') }}
                </p>
            </div>

            <ul
                class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
            >
                <li
                    v-for="network in networks"
                    :key="network.value"
                    class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm"
                    :data-testid="`public-network-${network.value}`"
                >
                    <PlatformLogo
                        :platform="network.value"
                        size="xs"
                        :title="null"
                    />
                    <span class="flex min-w-0 flex-col">
                        <span class="font-medium text-foreground">
                            {{ network.label }}
                        </span>
                        <span class="text-sm text-muted-foreground">
                            {{ $t(`channels.platforms.${network.value}`) }}
                        </span>
                    </span>
                </li>
            </ul>
        </div>
    </section>
</template>
