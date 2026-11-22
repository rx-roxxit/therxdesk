<script setup lang="ts">
import { Button } from '@/components/ui/button';
import date from '@/date';
import { activeLocale } from '@/language';
import { formatMoney } from '@/lib/money';
import type { Offer } from '@/types/offer';
import type {
    BillingInterval,
    WelcomePlan,
    WelcomePlanTrial,
} from '@/types/plan';

type Translate = (key: string, replace?: Record<string, string>) => string;

const props = defineProps<{
    plan: WelcomePlan;
    interval: BillingInterval;
    trial: WelcomePlanTrial | null;
    offer: Offer | null;
    processing: boolean;
    selected: boolean;
}>();

const emit = defineEmits<{
    (event: 'select'): void;
}>();

const amount = (): number | null => {
    if (props.interval === 'yearly') {
        return props.plan.yearly_amount;
    }

    return props.plan.monthly_amount;
};

const price = (): string => {
    const value = amount();

    if (value === null) {
        return '—';
    }

    return formatMoney(value, activeLocale.value);
};

const unitKey = (): string => {
    if (props.interval === 'yearly') {
        return 'billing.plans.per_year';
    }

    return 'billing.plans.per_month';
};

const priceWithUnit = ($t: Translate): string => `${price()}${$t(unitKey())}`;

/**
 * An offer's own button and terms when it starts a trial without a card,
 * otherwise the trial or full-price copy.
 */
const usesOfferCopy = (): boolean => {
    if (props.offer === null) {
        return false;
    }

    if (!props.offer.starts_without_card) {
        return false;
    }

    return props.trial !== null;
};

const buttonLabel = ($t: Translate): string => {
    if (usesOfferCopy() && props.offer !== null) {
        return $t(`offers.${props.offer.key}.checkout_button`);
    }

    if (props.trial === null) {
        return $t('rxdesk.welcome_plan.subscribe');
    }

    return $t('rxdesk.welcome_plan.start_trial', {
        days: String(props.trial.days),
    });
};

const termsLine = ($t: Translate): string => {
    if (usesOfferCopy() && props.offer !== null && props.offer.has_terms) {
        return $t(`offers.${props.offer.key}.terms`);
    }

    if (props.trial === null) {
        return $t('rxdesk.welcome_plan.subscribe_terms', {
            price: priceWithUnit($t),
        });
    }

    return $t('rxdesk.welcome_plan.trial_terms', {
        price: priceWithUnit($t),
        date: date.formatDate(props.trial.first_charge_at),
    });
};

const select = (): void => {
    emit('select');
};
</script>

<template>
    <article
        class="flex flex-col gap-4 rounded-xl border bg-card p-5 text-start transition-colors"
        :class="
            selected ? 'border-primary ring-1 ring-primary' : 'border-border'
        "
        :data-testid="`plan-card-${plan.slug}`"
        :data-selected="selected ? 'true' : undefined"
    >
        <div class="flex flex-col gap-2">
            <h3 class="text-lg leading-tight font-medium">{{ plan.name }}</h3>
            <p class="flex items-baseline gap-1 whitespace-nowrap">
                <span
                    class="text-3xl leading-none font-strong tracking-tight tabular-nums [word-spacing:-0.15em]"
                    :data-testid="`plan-price-${plan.slug}`"
                    >{{ price() }}</span
                >
                <span
                    class="text-base text-foreground"
                    :data-testid="`plan-price-unit-${plan.slug}`"
                    >{{ $t(unitKey()) }}</span
                >
            </p>
        </div>

        <ul class="flex flex-col gap-2 text-sm text-muted-foreground">
            <li
                v-if="plan.quota_label"
                :data-testid="`plan-storage-${plan.slug}`"
            >
                {{
                    $t('rxdesk.welcome_plan.storage', {
                        size: plan.quota_label,
                    })
                }}
            </li>
            <li
                v-if="plan.ai_credits_label"
                :data-testid="`plan-ai-credits-${plan.slug}`"
            >
                {{
                    $t('rxdesk.welcome_plan.ai_credits', {
                        credits: plan.ai_credits_label,
                    })
                }}
            </li>
            <li :data-testid="`plan-unlimited-${plan.slug}`">
                {{ $t('rxdesk.welcome_plan.unlimited') }}
            </li>
        </ul>

        <div class="mt-auto flex flex-col gap-2">
            <Button
                type="button"
                size="lg"
                class="w-full"
                :loading="processing"
                :data-testid="`plan-select-${plan.slug}`"
                @click="select"
            >
                {{ buttonLabel($t) }}
            </Button>
            <p
                class="text-center text-sm text-pretty text-muted-foreground"
                :data-testid="`plan-terms-${plan.slug}`"
            >
                {{ termsLine($t) }}
            </p>
        </div>
    </article>
</template>
