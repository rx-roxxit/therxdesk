<script setup lang="ts">
import { IconBuilding } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import PlanFeatureInfo from '@/components/billing/PlanFeatureInfo.vue';
import PlanFeatureList from '@/components/billing/PlanFeatureList.vue';
import PlanIntervalSwitch from '@/components/billing/PlanIntervalSwitch.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ChangePlanOffer, OfferPriceSlot } from '@/types/offer';
import {
    PLAN_CHANGE_LABELS,
    planChangeAction,
    type BillingInterval,
    type PlanOption,
} from '@/types/plan';

export type { PlanOption };

const props = withDefaults(
    defineProps<{
        plans: PlanOption[];
        interval: BillingInterval;
        offer?: ChangePlanOffer | null;
        currentPlanId?: string | null;
        currentInterval?: BillingInterval | null;
        disabledPlanIds?: string[];
        processing?: boolean;
    }>(),
    {
        offer: null,
        currentPlanId: null,
        currentInterval: null,
        disabledPlanIds: () => [],
        processing: false,
    },
);

const emit = defineEmits<{
    (event: 'update:interval', value: BillingInterval): void;
    (event: 'select', planId: string): void;
}>();

const offerSlot = (plan: PlanOption): OfferPriceSlot | null => {
    if (props.offer === null) {
        return null;
    }

    const row = props.offer.plans[plan.id];

    if (row === undefined) {
        return null;
    }

    return row[props.interval];
};

const hasOffer = (plan: PlanOption): boolean => offerSlot(plan) !== null;

const price = (plan: PlanOption): string => {
    const offer = offerSlot(plan);

    if (offer !== null) {
        return offer.discounted_per_month;
    }

    if (props.interval === 'yearly') {
        return plan.yearly_monthly_price ?? '—';
    }

    return plan.monthly_price ?? '—';
};

const regularPrice = (plan: PlanOption): string | null => {
    const offer = offerSlot(plan);

    if (offer === null) {
        return null;
    }

    return offer.regular_per_month;
};

const badge = (plan: PlanOption): string | null => {
    const offer = offerSlot(plan);

    if (offer === null) {
        return null;
    }

    return offer.badge;
};

const offerLine = (plan: PlanOption): string | null => {
    const offer = offerSlot(plan);

    if (offer === null) {
        return null;
    }

    return offer.line;
};

const yearlyTotal = (plan: PlanOption): string | null => {
    const offer = offerSlot(plan);

    if (offer !== null) {
        return offer.discounted;
    }

    return plan.yearly_price;
};

const isCurrentPlan = (plan: PlanOption): boolean =>
    plan.id === props.currentPlanId;

const currentPlan = computed(
    (): PlanOption | null =>
        props.plans.find((plan) => plan.id === props.currentPlanId) ?? null,
);

const isCurrentSelection = (plan: PlanOption): boolean =>
    isCurrentPlan(plan) &&
    props.currentInterval !== null &&
    props.currentInterval === props.interval;

const isDisabled = (plan: PlanOption): boolean =>
    props.processing ||
    isCurrentSelection(plan) ||
    props.disabledPlanIds.includes(plan.id);

const billingNote = (plan: PlanOption): string | null => {
    if (props.interval !== 'yearly') {
        return trans('billing.subscribe.billed_monthly');
    }

    const total = yearlyTotal(plan);

    if (total === null) {
        return null;
    }

    return trans('billing.plans.billed_yearly_total', { price: total });
};

const selectLabel = (plan: PlanOption): string => {
    if (isCurrentSelection(plan)) {
        return trans('billing.plans.current');
    }

    if (isCurrentPlan(plan)) {
        return props.interval === 'yearly'
            ? trans('billing.plans.switch_to_yearly')
            : trans('billing.plans.switch_to_monthly');
    }

    return trans(
        PLAN_CHANGE_LABELS[planChangeAction(currentPlan.value, plan)],
        {
            plan: plan.name,
        },
    );
};

const isUnlimited = (plan: PlanOption): boolean =>
    plan.workspace_limit === null;

const workspaceLabel = (plan: PlanOption): string =>
    isUnlimited(plan)
        ? trans('billing.plans.workspaces_unlimited')
        : trans('billing.plans.workspaces_one');
</script>

<template>
    <div class="@container space-y-6">
        <PlanIntervalSwitch
            :model-value="interval"
            @update:model-value="emit('update:interval', $event)"
        />

        <div class="grid items-stretch gap-4 @2xl:grid-cols-2">
            <article
                v-for="plan in plans"
                :key="plan.id"
                class="flex flex-col gap-4 rounded-xl border border-border bg-card p-5 text-start"
                :data-testid="`plan-card-${plan.slug}`"
            >
                <div class="flex flex-col gap-1">
                    <div class="flex w-full items-center justify-between gap-3">
                        <h3 class="text-lg leading-tight font-medium">
                            {{ plan.name }}
                        </h3>
                        <Badge
                            v-if="isCurrentSelection(plan)"
                            variant="secondary"
                            class="shrink-0"
                        >
                            {{ $t('billing.plans.current') }}
                        </Badge>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <p class="flex items-baseline gap-2">
                        <span
                            class="text-2xl leading-9 font-strong tracking-tight tabular-nums [word-spacing:-0.15em]"
                            :data-testid="`plan-price-${plan.slug}`"
                            >{{ price(plan) }}</span
                        >
                        <span class="text-base text-foreground">{{
                            $t('billing.plans.per_month')
                        }}</span>
                    </p>
                    <p
                        v-if="hasOffer(plan)"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Badge
                            variant="secondary"
                            :data-testid="`plan-offer-badge-${plan.slug}`"
                            >{{ badge(plan) }}</Badge
                        >
                        <span
                            class="line-through"
                            :data-testid="`plan-offer-regular-${plan.slug}`"
                            >{{ regularPrice(plan) }}</span
                        >
                    </p>
                    <p
                        class="text-sm text-muted-foreground"
                        :data-testid="`plan-price-note-${plan.slug}`"
                    >
                        {{ billingNote(plan) }}
                    </p>
                    <p
                        v-if="hasOffer(plan)"
                        class="text-sm text-muted-foreground"
                        :data-testid="`plan-offer-line-${plan.slug}`"
                    >
                        {{ offerLine(plan) }}
                    </p>
                </div>

                <Button
                    type="button"
                    size="lg"
                    variant="outline"
                    class="w-full"
                    :disabled="isDisabled(plan)"
                    :data-testid="`plan-select-${plan.slug}`"
                    @click="emit('select', plan.id)"
                >
                    {{ selectLabel(plan) }}
                </Button>

                <div
                    class="flex items-center gap-3 rounded-lg bg-muted px-3 py-2"
                    :data-testid="`plan-highlight-${plan.slug}`"
                >
                    <span
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-border bg-card"
                    >
                        <IconBuilding class="size-4" />
                    </span>
                    <p
                        class="inline-flex items-start gap-1.5 text-sm leading-5 font-medium text-foreground"
                    >
                        <span>{{ workspaceLabel(plan) }}</span>
                        <PlanFeatureInfo
                            :label="$t('billing.plans.workspaces_tooltip')"
                            :testid="`plan-workspaces-info-${plan.slug}`"
                        />
                    </p>
                </div>

                <PlanFeatureList
                    :scope="plan.slug"
                    class="flex-1 border-t border-border pt-4"
                />
            </article>
        </div>
    </div>
</template>
