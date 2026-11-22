<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import date from '@/date';
import type { OfferPriceSlot, OfferTrialSlot } from '@/types/offer';
import type { BillingInterval, WelcomePlan } from '@/types/plan';

type Translate = (key: string, replace?: Record<string, string>) => string;

const props = defineProps<{
    plan: WelcomePlan;
    interval: BillingInterval;
    processing: boolean;
    selected: boolean;
}>();

const emit = defineEmits<{
    (event: 'select'): void;
}>();

const offerSlot = (): OfferPriceSlot | null => props.plan.offer[props.interval];

const hasOffer = (): boolean => offerSlot() !== null;

const trialSlot = (): OfferTrialSlot | null => props.plan.trial[props.interval];

const cardClass = (): string => {
    if (props.selected) {
        return 'border-primary ring-1 ring-primary';
    }

    return 'border-border';
};

const selectedAttribute = (): string | undefined => {
    if (props.selected) {
        return 'true';
    }

    return undefined;
};

const price = (): string => {
    const offer = offerSlot();

    if (offer !== null) {
        return offer.discounted;
    }

    if (props.interval === 'yearly') {
        return props.plan.yearly_price ?? '—';
    }

    return props.plan.monthly_price ?? '—';
};

const regularPrice = (): string | null => offerSlot()?.regular ?? null;

const badge = (): string | null => offerSlot()?.badge ?? null;

const offerLine = (): string | null => offerSlot()?.line ?? null;

const unitKey = (): string => {
    if (props.interval === 'yearly') {
        return 'billing.plans.per_year';
    }

    return 'billing.plans.per_month';
};

const priceWithUnit = ($t: Translate): string => `${price()}${$t(unitKey())}`;

const buttonLabel = ($t: Translate): string => {
    const trial = trialSlot();

    if (trial === null) {
        return $t('rxdesk.welcome_plan.subscribe');
    }

    return $t('rxdesk.welcome_plan.start_trial', {
        days: String(trial.days),
    });
};

const termsLine = ($t: Translate): string => {
    const line = offerLine();

    if (line !== null) {
        return line;
    }

    const trial = trialSlot();

    if (trial === null) {
        return $t('rxdesk.welcome_plan.subscribe_terms', {
            price: priceWithUnit($t),
        });
    }

    return $t('rxdesk.welcome_plan.trial_terms', {
        price: priceWithUnit($t),
        date: date.formatDate(trial.first_charge_at),
    });
};

const select = (): void => {
    emit('select');
};
</script>

<template>
    <article
        class="flex flex-col gap-4 rounded-xl border bg-card p-5 text-start transition-colors"
        :class="cardClass()"
        :data-testid="`plan-card-${plan.slug}`"
        :data-selected="selectedAttribute()"
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
            <p
                v-if="hasOffer()"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <Badge
                    variant="secondary"
                    :data-testid="`plan-offer-badge-${plan.slug}`"
                    >{{ badge() }}</Badge
                >
                <span
                    class="line-through"
                    :data-testid="`plan-offer-regular-${plan.slug}`"
                    >{{ regularPrice() }}</span
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
