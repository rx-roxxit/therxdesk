<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

import PlanIntervalSwitch from '@/components/billing/PlanIntervalSwitch.vue';
import VerifyEmailDialog from '@/components/welcome/VerifyEmailDialog.vue';
import WelcomeOfferBanner from '@/components/welcome/WelcomeOfferBanner.vue';
import WelcomePlanCard from '@/components/welcome/WelcomePlanCard.vue';
import WelcomeLayout from '@/layouts/WelcomeLayout.vue';
import { store } from '@/routes/app/welcome/plan';
import type { SharedData } from '@/types';
import type { Offer } from '@/types/offer';
import type {
    BillingInterval,
    WelcomePlan,
    WelcomePlanSelection,
    WelcomePlanTrial,
} from '@/types/plan';

const props = defineProps<{
    plans: WelcomePlan[];
    selected: WelcomePlanSelection;
    offer: Offer | null;
    trial: WelcomePlanTrial | null;
}>();

const page = usePage<SharedData>();
const interval = ref<BillingInterval>(props.selected.interval);
const processingPlan = ref<string | null>(null);
const verifyOpen = ref(false);

const form = useForm({
    plan: props.selected.plan,
    interval: props.selected.interval,
});

const isSelected = (plan: WelcomePlan): boolean =>
    plan.slug === props.selected.plan;

const isProcessing = (plan: WelcomePlan): boolean =>
    processingPlan.value === plan.slug;

const submit = (slug: string): void => {
    processingPlan.value = slug;
    form.plan = slug;
    form.interval = interval.value;

    form.submit(store(), {
        onFinish: () => {
            processingPlan.value = null;
        },
    });
};

const selectPlan = (slug: string): void => {
    if (form.processing) {
        return;
    }

    if (page.props.auth.user.email_verified_at === null) {
        verifyOpen.value = true;

        return;
    }

    submit(slug);
};

const closeVerifyDialog = (open: boolean): void => {
    verifyOpen.value = open;
};
</script>

<template>
    <Head :title="$t('rxdesk.welcome_plan.title')" />

    <WelcomeLayout
        :title="$t('rxdesk.welcome_plan.title')"
        :description="$t('rxdesk.welcome_plan.description')"
        step="plan"
        size="6xl"
    >
        <div class="flex w-full flex-col gap-6">
            <WelcomeOfferBanner
                v-if="offer?.has_banner"
                :offer-key="offer.key"
            />

            <PlanIntervalSwitch v-model="interval" />

            <div
                class="grid grid-cols-1 items-stretch gap-4 min-[1081px]:grid-cols-4 sm:grid-cols-2"
            >
                <WelcomePlanCard
                    v-for="plan in plans"
                    :key="plan.id"
                    :plan="plan"
                    :interval="interval"
                    :trial="trial"
                    :offer="offer"
                    :selected="isSelected(plan)"
                    :processing="isProcessing(plan)"
                    @select="selectPlan(plan.slug)"
                />
            </div>
        </div>

        <VerifyEmailDialog
            :open="verifyOpen"
            :email="page.props.auth.user.email"
            @update:open="closeVerifyDialog"
        />
    </WelcomeLayout>
</template>
