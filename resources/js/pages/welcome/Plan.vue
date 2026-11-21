<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import PlanFeatureList from '@/components/billing/PlanFeatureList.vue';
import WelcomeContinueButton from '@/components/welcome/WelcomeContinueButton.vue';
import WelcomeOfferBanner from '@/components/welcome/WelcomeOfferBanner.vue';
import { activeLocale } from '@/language';
import WelcomeLayout from '@/layouts/WelcomeLayout.vue';
import { formatMoney } from '@/lib/money';
import { store } from '@/routes/app/welcome/plan';
import type { Offer } from '@/types/offer';
import type { PlanOption } from '@/types/plan';

const props = defineProps<{
    plan: PlanOption;
    offer: Offer | null;
}>();

interface PriceCopy {
    priceKey: string | null;
    unit: string | null;
    period: string | null;
    checkoutButton: string;
    terms: string;
}

const FULL_PRICE_COPY: PriceCopy = {
    priceKey: null,
    unit: 'billing.plans.per_month',
    period: null,
    checkoutButton: 'welcome.plan_checkout_button',
    terms: 'welcome.plan_cancel_anytime',
};

const monthlyPrice = computed((): string => {
    if (props.plan.monthly_amount === null) {
        return '';
    }

    return formatMoney(props.plan.monthly_amount, activeLocale.value);
});

const priceCopy = computed((): PriceCopy => {
    if (!props.offer?.has_checkout_copy) {
        return FULL_PRICE_COPY;
    }

    const offerCopy = `offers.${props.offer.key}`;

    return {
        priceKey: `${offerCopy}.price`,
        unit: props.offer.has_unit ? `${offerCopy}.unit` : null,
        period: `${offerCopy}.period`,
        checkoutButton: `${offerCopy}.checkout_button`,
        terms: props.offer.has_terms
            ? `${offerCopy}.terms`
            : 'welcome.plan_terms',
    };
});

const form = useForm({});

const submit = (): void => {
    if (form.processing) {
        return;
    }

    form.submit(store());
};
</script>

<template>
    <Head :title="$t('welcome.plan_title')" />

    <WelcomeLayout
        :title="$t('welcome.plan_title')"
        :description="$t('welcome.plan_description')"
        step="plan"
    >
        <WelcomeOfferBanner v-if="offer?.has_banner" :offer-key="offer.key" />

        <article
            class="grid overflow-hidden rounded-xl text-start sm:grid-cols-[2fr_3fr]"
            :data-testid="`plan-card-${plan.slug}`"
        >
            <div
                class="flex flex-col justify-center gap-1 bg-primary-subtle p-5 sm:p-6 dark:bg-primary-selected"
            >
                <s
                    v-if="priceCopy.period"
                    class="text-sm text-muted-foreground"
                    :data-testid="`plan-price-original-${plan.slug}`"
                    >{{ monthlyPrice }}{{ $t('billing.plans.per_month') }}</s
                >
                <p class="flex items-baseline gap-1 whitespace-nowrap">
                    <span
                        class="text-4xl leading-none font-strong tracking-tight tabular-nums [word-spacing:-0.15em] sm:text-5xl"
                        :data-testid="`plan-price-${plan.slug}`"
                    >
                        <template v-if="priceCopy.priceKey">{{
                            $t(priceCopy.priceKey)
                        }}</template>
                        <template v-else>{{ monthlyPrice }}</template>
                    </span>
                    <span
                        v-if="priceCopy.unit"
                        class="text-base text-foreground"
                        :data-testid="`plan-price-unit-${plan.slug}`"
                        >{{ $t(priceCopy.unit) }}</span
                    >
                </p>
                <span
                    v-if="priceCopy.period"
                    class="text-base text-foreground"
                    :data-testid="`plan-price-suffix-${plan.slug}`"
                    >{{ $t(priceCopy.period) }}</span
                >
            </div>

            <PlanFeatureList
                :scope="plan.slug"
                spacious
                class="rounded-b-xl border border-border bg-card p-5 max-sm:border-t-0 sm:rounded-e-xl sm:rounded-es-none sm:border-s-0 sm:p-6"
            />
        </article>

        <template #actions>
            <WelcomeContinueButton
                :processing="form.processing"
                :disabled="form.processing"
                :label="$t(priceCopy.checkoutButton)"
                testid="welcome-plan-continue"
                @continue="submit"
            />
            <p
                class="text-center text-sm text-muted-foreground"
                data-testid="welcome-plan-terms"
            >
                {{
                    $t(priceCopy.terms, {
                        price: monthlyPrice,
                    })
                }}
            </p>
        </template>
    </WelcomeLayout>
</template>
