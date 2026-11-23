<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import { IconLoader2 } from '@tabler/icons-vue';
import { computed, onMounted, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import { index as billingIndex } from '@/routes/app/billing';
import { index as postsIndex } from '@/routes/app/posts';
import { plan as welcomePlan } from '@/routes/app/welcome';
import type { SharedData } from '@/types';

type PurchaseDocumentRow = {
    label: string;
    value: string;
};

type PurchaseDocument = {
    title: string;
    rows: PurchaseDocumentRow[];
    cancellation: string;
};

const props = defineProps<{
    ready: boolean;
    returnsToBilling: boolean;
    countryBlocked: boolean;
    purchaseDocument: PurchaseDocument | null;
}>();

const page = usePage<SharedData>();

// Poll `auth` so `auth.plan` is set after the webhook writes plan_id,
// `countryBlocked` so a cancelled checkout leaves Processing at once, and
// `purchaseDocument` so a Thai checkout's document appears once the webhook
// has recorded its billing country.
const { stop } = usePoll(2000, {
    only: ['ready', 'auth', 'countryBlocked', 'purchaseDocument'],
});

const finishing = ref(false);

const purchaseReady = computed(
    (): boolean => props.ready && page.props.auth.plan !== null,
);

const showingDocument = computed(
    (): boolean => props.purchaseDocument !== null,
);

const titleKey = computed((): string =>
    showingDocument.value
        ? 'rxdesk.billing.purchase_document.title'
        : 'billing.processing.title',
);

const descriptionKey = computed((): string =>
    showingDocument.value
        ? 'rxdesk.billing.purchase_document.description'
        : 'billing.processing.description',
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
// A Thai purchase document stays on screen until the buyer continues, so they
// can read it.
const completePurchase = (): void => {
    if (finishing.value || showingDocument.value) {
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
    <Head :title="$t(titleKey)" />

    <AuthSplitLayout :title="$t(titleKey)" :description="$t(descriptionKey)">
        <article
            v-if="showingDocument"
            data-testid="thai-purchase-document"
            class="flex flex-col gap-4 rounded-xl border border-border bg-card p-6 text-start"
        >
            <h2
                class="font-heading text-lg font-medium tracking-tight text-foreground"
            >
                {{ purchaseDocument.title }}
            </h2>

            <dl class="flex flex-col gap-2 text-sm">
                <div
                    v-for="row in purchaseDocument.rows"
                    :key="row.label"
                    class="grid grid-cols-1 gap-0.5 sm:grid-cols-[minmax(0,9rem)_1fr] sm:gap-3"
                >
                    <dt class="font-medium text-muted-foreground">
                        {{ row.label }}
                    </dt>
                    <dd class="whitespace-pre-line text-foreground">
                        {{ row.value }}
                    </dd>
                </div>
            </dl>

            <p
                class="mt-1 text-sm leading-6 font-bold text-foreground"
                data-testid="thai-purchase-document-cancellation"
            >
                {{ purchaseDocument.cancellation }}
            </p>

            <Button
                type="button"
                class="w-full"
                data-testid="purchase-document-continue"
                @click="goNext"
            >
                {{ $t('rxdesk.billing.purchase_document.continue') }}
            </Button>
        </article>

        <div
            v-else
            class="flex justify-center"
            data-testid="billing-processing"
        >
            <IconLoader2
                class="size-6 animate-spin text-primary-strong motion-reduce:animate-none"
                aria-hidden="true"
            />
        </div>
    </AuthSplitLayout>
</template>
