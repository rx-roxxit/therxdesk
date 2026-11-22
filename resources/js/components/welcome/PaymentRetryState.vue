<script setup lang="ts">
import { usePoll } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';

defineProps<{
    updateCardUrl: string;
}>();

// The charge is retried at Stripe and the webhook lifts the pause; once it
// lands, the next reload redirects into the app. Only `auth` is reloaded: the
// redirect decision is server-side.
usePoll(10000, { only: ['auth'] });
</script>

<template>
    <div
        class="flex flex-col items-center gap-4 text-center"
        data-testid="plan-payment-retry"
    >
        <Button
            as="a"
            :href="updateCardUrl"
            size="lg"
            data-testid="plan-payment-retry-update-card"
        >
            {{ $t('rxdesk.welcome_plan.payment_retry.update_card') }}
        </Button>
    </div>
</template>
