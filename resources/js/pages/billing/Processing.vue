<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import { IconLoader2 } from '@tabler/icons-vue';
import { computed, onMounted, ref, watch } from 'vue';

import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import { index as billingIndex } from '@/routes/app/billing';
import { index as postsIndex } from '@/routes/app/posts';
import { plan as welcomePlan } from '@/routes/app/welcome';
import type { SharedData } from '@/types';

const props = defineProps<{
    ready: boolean;
    returnsToBilling: boolean;
    countryBlocked: boolean;
}>();

const page = usePage<SharedData>();

// Poll `auth` so `auth.plan` is set after the webhook writes plan_id, and
// `countryBlocked` so a cancelled checkout leaves Processing at once.
const { stop } = usePoll(2000, {
    only: ['ready', 'auth', 'countryBlocked'],
});

const finishing = ref(false);

const purchaseReady = computed(
    (): boolean => props.ready && page.props.auth.plan !== null,
);

// The checkout's billing country is blocked: the webhook cancelled it, so the
// plan step shows the notice instead of a spinner that never finishes.
const goToPlan = (): void => {
    if (!props.countryBlocked) {
        return;
    }

    stop();
    router.visit(welcomePlan.url(), { replace: true });
};

const goNext = (): void => {
    if (props.returnsToBilling) {
        router.visit(billingIndex.url(), { replace: true });

        return;
    }

    router.visit(postsIndex.url(), { replace: true });
};

// Card-required trials are already `subscribed()` (`trialing`) on first paint.
const completePurchase = (): void => {
    if (finishing.value) {
        return;
    }
    finishing.value = true;
    stop();
    goNext();
};

watch(purchaseReady, (ready) => {
    if (ready) {
        completePurchase();
    }
});

watch(
    () => props.countryBlocked,
    (blocked) => {
        if (blocked) {
            goToPlan();
        }
    },
);

onMounted(() => {
    if (props.countryBlocked) {
        goToPlan();

        return;
    }

    if (purchaseReady.value) {
        completePurchase();
    }
});
</script>

<template>
    <Head :title="$t('billing.processing.page_title')" />

    <AuthSplitLayout
        :title="$t('billing.processing.title')"
        :description="$t('billing.processing.description')"
    >
        <div class="flex justify-center" data-testid="billing-processing">
            <IconLoader2
                class="size-6 animate-spin text-primary-strong motion-reduce:animate-none"
                aria-hidden="true"
            />
        </div>
    </AuthSplitLayout>
</template>
