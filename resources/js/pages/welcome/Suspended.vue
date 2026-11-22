<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { IconAlertTriangle } from '@tabler/icons-vue';

import { Button } from '@/components/ui/button';
import WelcomeLayout from '@/layouts/WelcomeLayout.vue';

// The System admin lifts a Suspension outside the app. Once it is gone, the
// next reload redirects into the app, so only `auth` is reloaded here.
usePoll(10000, { only: ['auth'] });

const supportMailto = 'mailto:support@therxdesk.com';
</script>

<template>
    <Head :title="$t('rxdesk.suspension.title')" />

    <WelcomeLayout :title="$t('rxdesk.suspension.title')" size="lg">
        <div
            class="flex flex-col items-center gap-4 text-center"
            data-testid="welcome-suspended"
        >
            <div
                class="w-full rounded-xl border border-destructive bg-destructive/10 p-4 text-start"
                data-testid="suspended-banner"
            >
                <div class="flex items-center gap-2 text-destructive">
                    <IconAlertTriangle class="size-4 shrink-0" />
                    <span class="text-sm font-medium">
                        {{ $t('rxdesk.suspension.title') }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ $t('rxdesk.suspension.description') }}
                </p>
                <Button
                    as="a"
                    :href="supportMailto"
                    variant="destructive"
                    size="sm"
                    class="mt-2"
                >
                    {{ $t('rxdesk.suspension.support_action') }}
                </Button>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ $t('rxdesk.suspension.auto') }}
            </p>
        </div>
    </WelcomeLayout>
</template>
