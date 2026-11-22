<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

import PlanIntervalSwitch from '@/components/billing/PlanIntervalSwitch.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import PaymentRetryState from '@/components/welcome/PaymentRetryState.vue';
import VerifyEmailDialog from '@/components/welcome/VerifyEmailDialog.vue';
import WelcomeContinueButton from '@/components/welcome/WelcomeContinueButton.vue';
import WelcomeOfferBanner from '@/components/welcome/WelcomeOfferBanner.vue';
import WelcomePlanCard from '@/components/welcome/WelcomePlanCard.vue';
import date from '@/date';
import WelcomeLayout from '@/layouts/WelcomeLayout.vue';
import { index as scheduleIndex } from '@/routes/app/posts';
import { store } from '@/routes/app/welcome/plan';
import type { SharedData, WelcomeStep } from '@/types';
import type { ComplimentaryPlan } from '@/types/billing';
import type { Offer } from '@/types/offer';
import type {
    BillingInterval,
    WelcomePlan,
    WelcomePlanRetry,
    WelcomePlanSelection,
    WelcomePlanTrial,
} from '@/types/plan';

type Translate = (key: string, replace?: Record<string, string>) => string;

const props = defineProps<{
    plans: WelcomePlan[];
    selected: WelcomePlanSelection;
    offer: Offer | null;
    trial: WelcomePlanTrial | null;
    retry: WelcomePlanRetry | null;
    countryBlocked: boolean;
    complimentary: ComplimentaryPlan | null;
}>();

const layoutTitle = ($t: Translate): string => {
    if (props.complimentary !== null) {
        return $t('rxdesk.complimentary.plan_given');
    }

    if (props.retry !== null) {
        return $t('rxdesk.welcome_plan.payment_retry.title');
    }

    return $t('rxdesk.welcome_plan.title');
};

const layoutDescription = ($t: Translate): string | undefined => {
    if (props.complimentary !== null) {
        return undefined;
    }

    if (props.retry !== null) {
        return $t('rxdesk.welcome_plan.payment_retry.description');
    }

    return $t('rxdesk.welcome_plan.description');
};

const layoutStep = (): WelcomeStep | undefined => {
    if (props.retry !== null) {
        return undefined;
    }

    return 'plan';
};

const layoutSize = (): 'lg' | '6xl' => {
    if (props.complimentary !== null) {
        return 'lg';
    }

    if (props.retry !== null) {
        return 'lg';
    }

    return '6xl';
};

const complimentaryUntilLine = ($t: Translate): string => {
    const endsAt = props.complimentary?.ends_at ?? null;

    if (endsAt === null) {
        return $t('rxdesk.complimentary.no_end');
    }

    return $t('rxdesk.complimentary.until', {
        date: date.formatDate(endsAt),
    });
};

const continueToApp = (): void => {
    router.visit(scheduleIndex());
};

const page = usePage<SharedData>();
const interval = ref<BillingInterval>(props.selected.interval);
const processingPlan = ref<string | null>(null);
const verifyOpen = ref(false);

const form = useForm({
    plan: props.selected.plan,
    interval: props.selected.interval,
});

const isSelected = (plan: WelcomePlan): boolean => plan.slug === form.plan;

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
    <Head :title="layoutTitle($t)" />

    <WelcomeLayout
        :title="layoutTitle($t)"
        :description="layoutDescription($t)"
        :step="layoutStep()"
        :size="layoutSize()"
    >
        <Alert
            v-if="countryBlocked"
            variant="destructive"
            class="w-full"
            data-testid="welcome-country-blocked"
        >
            <AlertDescription>{{
                $t('rxdesk.country.blocked')
            }}</AlertDescription>
        </Alert>

        <div
            v-else-if="complimentary !== null"
            class="flex w-full flex-col gap-6"
        >
            <div
                class="mx-auto flex w-full max-w-md flex-col gap-3 rounded-xl border border-border bg-card p-6 text-start"
                data-testid="welcome-plan-complimentary"
            >
                <p
                    class="text-xl leading-tight font-emphasis text-foreground"
                    data-testid="welcome-plan-complimentary-plan"
                >
                    {{ complimentary.plan_name }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ $t('rxdesk.complimentary.plan_given') }}
                </p>
                <p
                    v-if="complimentary.ends_at"
                    class="text-sm text-foreground"
                    data-testid="welcome-plan-complimentary-until"
                >
                    {{ complimentaryUntilLine($t) }}
                </p>
                <p
                    v-else
                    class="text-sm text-foreground"
                    data-testid="welcome-plan-complimentary-no-end"
                >
                    {{ $t('rxdesk.complimentary.no_end') }}
                </p>
            </div>
        </div>

        <div v-else-if="retry === null" class="flex w-full flex-col gap-6">
            <WelcomeOfferBanner
                v-if="offer?.show_banner"
                :name="offer.name"
                :line="offer.lines[interval]"
            />

            <PlanIntervalSwitch v-model="interval" />

            <div
                class="grid grid-cols-1 items-stretch gap-4 min-[640px]:max-[1080px]:grid-cols-2 plan:grid-cols-4"
            >
                <WelcomePlanCard
                    v-for="plan in plans"
                    :key="plan.id"
                    :plan="plan"
                    :interval="interval"
                    :selected="isSelected(plan)"
                    :processing="isProcessing(plan)"
                    @select="selectPlan(plan.slug)"
                />
            </div>
        </div>

        <PaymentRetryState v-else :update-card-url="retry.update_card_url" />

        <template v-if="complimentary !== null" #actions>
            <WelcomeContinueButton
                :disabled="false"
                testid="welcome-plan-complimentary-continue"
                @continue="continueToApp"
            />
        </template>

        <VerifyEmailDialog
            :open="verifyOpen"
            :email="page.props.auth.user.email"
            @update:open="closeVerifyDialog"
        />
    </WelcomeLayout>
</template>
