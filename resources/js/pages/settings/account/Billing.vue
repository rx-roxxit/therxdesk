<script setup lang="ts">
import {
    Head,
    InfiniteScroll,
    router,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    IconCreditCard,
    IconDiscount,
    IconDownload,
    IconFileText,
    IconSparkles,
    IconWallet,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import PlanPicker from '@/components/billing/PlanPicker.vue';
import EmptyState from '@/components/EmptyState.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableLoadMore,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import date from '@/date';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { cardBrand } from '@/lib/cardBrands';
import { formatNumber } from '@/lib/utils';
import {
    changePlan as changePlanRoute,
    checkout as checkoutRoute,
    index,
    portal,
} from '@/routes/app/billing';
import type { AiCredits, AuthPlan, SharedData, StorageSummary } from '@/types';
import type {
    ComplimentaryPlan,
    Invoice,
    PaymentMethod,
    Subscription,
    SubscriptionDiscount,
} from '@/types/billing';
import type { ChangePlanOffer, OfferPriceSlot } from '@/types/offer';
import {
    DEFAULT_BILLING_INTERVAL,
    deniedPlanIdsFor,
    planChangeDirection,
    type BillingInterval,
    type PlanChangeDirection,
    type PlanOption,
} from '@/types/plan';

interface ScrollInvoices {
    data: Invoice[];
}

interface StatusLine {
    key: string;
    params: Record<string, string>;
}

type BillingTab = 'plan' | 'invoices';

type Translate = (key: string, replace?: Record<string, string>) => string;

const BILLING_TABS: BillingTab[] = ['plan', 'invoices'];

type SubscriptionStatus = 'trial' | 'past_due' | 'cancelling' | 'active';

const STATUS_BADGES: Record<SubscriptionStatus, BadgeVariants['variant']> = {
    active: 'success',
    trial: 'info',
    cancelling: 'warning',
    past_due: 'destructive-subtle',
};

const props = defineProps<{
    hasSubscription: boolean;
    onTrial: boolean;
    trialEndsAt: string | null;
    subscription: Subscription | null;
    plan: PlanOption | null;
    workspaceCount: number;
    invoices: ScrollInvoices;
    paymentMethods: PaymentMethod[];
    discount: SubscriptionDiscount | null;
    accountBalance: string | null;
    renewsAt: string | null;
    changePlanOffer: ChangePlanOffer | null;
    complimentary: ComplimentaryPlan | null;
}>();

const hasComplimentary = computed((): boolean => props.complimentary !== null);

const showsPlanSection = computed(
    (): boolean =>
        props.hasSubscription || props.onTrial || hasComplimentary.value,
);

const showsChangePlan = computed(
    (): boolean => props.hasSubscription && hasComplimentary.value === false,
);

const showsSubscribe = computed(
    (): boolean =>
        props.hasSubscription === false && hasComplimentary.value === false,
);

const showsSubscriptionSection = computed(
    (): boolean => props.hasSubscription && hasComplimentary.value === false,
);

const billingTabs = computed((): BillingTab[] => {
    if (hasComplimentary.value) {
        return ['plan'];
    }

    return BILLING_TABS;
});

const complimentaryUntilLine = ($t: Translate): string => {
    const endsAt = props.complimentary?.ends_at ?? null;

    if (endsAt === null) {
        return $t('rxdesk.complimentary.no_end');
    }

    return $t('rxdesk.complimentary.until', {
        date: date.formatDate(endsAt),
    });
};

const discountKey = computed((): string => {
    if (props.discount?.duration === 'once') {
        return 'billing.current_plan.discount_once';
    }

    return props.discount?.ends_at
        ? 'billing.current_plan.discount_until'
        : 'billing.current_plan.discount_forever';
});

const page = usePage<SharedData>();
const plans = computed((): PlanOption[] => page.props.plans ?? []);
const storage = computed(
    (): StorageSummary | null => page.props.storage ?? null,
);
const credits = computed((): AiCredits | null => page.props.aiCredits ?? null);
const authPlan = computed((): AuthPlan | null => page.props.auth.plan);
const currentInterval = computed(
    (): BillingInterval => authPlan.value?.interval ?? DEFAULT_BILLING_INTERVAL,
);

const subscriptionStatus = computed((): SubscriptionStatus | null => {
    if (hasComplimentary.value) {
        return null;
    }

    if (props.subscription?.stripe_status === 'past_due') {
        return 'past_due';
    }

    if (props.subscription?.ends_at) {
        return 'cancelling';
    }

    if (props.onTrial) {
        return 'trial';
    }

    if (props.subscription?.stripe_status === 'active') {
        return 'active';
    }

    return null;
});

const deniedPlanIds = computed((): string[] =>
    deniedPlanIdsFor(plans.value, props.workspaceCount),
);

const selectedInterval = ref<BillingInterval>(currentInterval.value);
const isPlanDialogOpen = ref(false);
const isConfirmDialogOpen = ref(false);
const pendingPlanId = ref<string | null>(null);

const currentPrice = computed((): string | null => {
    if (!props.plan) {
        return null;
    }

    if (currentInterval.value === 'yearly') {
        return props.plan.yearly_price;
    }

    return props.plan.monthly_price;
});

const showsPrice = computed(
    (): boolean =>
        hasComplimentary.value === false && currentPrice.value !== null,
);

const priceSuffixKey = computed((): string =>
    currentInterval.value === 'yearly'
        ? 'billing.plans.per_year'
        : 'billing.plans.per_month',
);

/**
 * The strip under the current plan: one line from the Account's state, and
 * nothing when the state has no line (an active Plan whose renewal we cannot
 * read from Stripe).
 */
const statusStrip = computed((): StatusLine | null => {
    if (subscriptionStatus.value === 'trial') {
        if (props.trialEndsAt === null || currentPrice.value === null) {
            return null;
        }

        return {
            key:
                currentInterval.value === 'yearly'
                    ? 'rxdesk.billing.status.trial_yearly'
                    : 'rxdesk.billing.status.trial_monthly',
            params: {
                date: date.formatDateTime(props.trialEndsAt),
                price: currentPrice.value,
            },
        };
    }

    if (subscriptionStatus.value === 'active') {
        if (props.renewsAt === null || currentPrice.value === null) {
            return null;
        }

        return {
            key:
                currentInterval.value === 'yearly'
                    ? 'rxdesk.billing.status.renews_yearly'
                    : 'rxdesk.billing.status.renews_monthly',
            params: {
                date: date.formatDate(props.renewsAt),
                price: currentPrice.value,
            },
        };
    }

    if (subscriptionStatus.value === 'cancelling') {
        const endsAt = props.subscription?.ends_at ?? null;

        if (endsAt === null) {
            return null;
        }

        return {
            key: 'rxdesk.billing.status.cancelling',
            params: { date: date.formatDate(endsAt) },
        };
    }

    if (subscriptionStatus.value === 'past_due') {
        return { key: 'billing.past_due_notice.description', params: {} };
    }

    return null;
});

const storageLine = ($t: Translate): string => {
    const value = storage.value;

    if (value === null) {
        return '';
    }

    return $t('rxdesk.storage_bar.usage', {
        used: value.used_label,
        limit: value.limit_label,
    });
};

const aiLine = ($t: Translate): string => {
    const value = credits.value;

    if (value === null) {
        return '';
    }

    return $t('rxdesk.ai_credits.usage_line', {
        used: formatNumber(value.used),
        total: formatNumber(value.total),
        date: date.formatDate(value.renews_at),
    });
};

const billingTab = (value: unknown): BillingTab =>
    billingTabs.value.find((tab) => tab === value) ?? 'plan';

const tab = ref<BillingTab>(
    billingTab(
        new URL(page.url, window.location.origin).searchParams.get('tab'),
    ),
);

const selectTab = (value: string | number): void => {
    tab.value = billingTab(value);

    router.replace({
        url: index({
            query: tab.value === 'plan' ? {} : { tab: tab.value },
        }).url,
        preserveScroll: true,
        preserveState: true,
    });
};

const planForm = useForm<{
    plan_id: string | null;
    interval: BillingInterval;
}>({
    plan_id: null,
    interval: DEFAULT_BILLING_INTERVAL,
});

const openPlanDialog = (): void => {
    selectedInterval.value = currentInterval.value;
    isPlanDialogOpen.value = true;
};

const checkoutForm = useForm({});

const subscribe = (): void => {
    checkoutForm.post(checkoutRoute.url());
};

const pendingPlan = computed((): PlanOption | null => {
    if (pendingPlanId.value === null) {
        return null;
    }

    return plans.value.find((plan) => plan.id === pendingPlanId.value) ?? null;
});

const pendingPlanName = computed((): string => {
    const plan = pendingPlan.value;

    if (plan === null) {
        return '';
    }

    return plan.name;
});

const offerSlot = (
    plan: PlanOption,
    interval: BillingInterval,
): OfferPriceSlot | null => {
    if (props.changePlanOffer === null) {
        return null;
    }

    const row = props.changePlanOffer.plans[plan.id];

    if (row === undefined) {
        return null;
    }

    return row[interval];
};

const pendingDirection = computed((): PlanChangeDirection => {
    const plan = pendingPlan.value;

    if (plan === null) {
        return 'same';
    }

    return planChangeDirection(
        props.plan,
        currentInterval.value,
        plan,
        selectedInterval.value,
    );
});

const pendingPrice = computed((): string | null => {
    const plan = pendingPlan.value;

    if (plan === null) {
        return null;
    }

    const slot = offerSlot(plan, selectedInterval.value);

    if (slot !== null) {
        if (selectedInterval.value === 'yearly') {
            return slot.discounted;
        }

        return slot.discounted_per_month;
    }

    if (selectedInterval.value === 'yearly') {
        return plan.yearly_price;
    }

    return plan.monthly_price;
});

const pendingPriceSuffixKey = computed((): string =>
    selectedInterval.value === 'yearly'
        ? 'billing.plans.per_year'
        : 'billing.plans.per_month',
);

const pendingTimingKey = computed((): string | null => {
    if (props.onTrial) {
        return 'rxdesk.billing.confirm.no_charge_trial';
    }

    if (pendingDirection.value === 'upgrade') {
        return 'rxdesk.billing.confirm.charge_today';
    }

    if (pendingDirection.value === 'downgrade') {
        return 'rxdesk.billing.confirm.credit_balance';
    }

    return null;
});

const pendingOffer = computed((): StatusLine | null => {
    if (props.changePlanOffer === null) {
        return null;
    }

    const plan = pendingPlan.value;

    if (plan === null) {
        return null;
    }

    const slot = offerSlot(plan, selectedInterval.value);
    const kept = slot !== null;

    return {
        key: kept
            ? 'rxdesk.billing.confirm.offer_kept'
            : 'rxdesk.billing.confirm.offer_dropped',
        params: { name: props.changePlanOffer.name },
    };
});

const pendingQuotaWarning = computed((): { limit: string } | null => {
    const plan = pendingPlan.value;
    const usage = storage.value;

    if (plan === null || usage === null) {
        return null;
    }

    if (plan.quota_bytes === null || plan.quota_label === null) {
        return null;
    }

    if (usage.used <= plan.quota_bytes) {
        return null;
    }

    return { limit: plan.quota_label };
});

const pendingAiWarning = computed((): { date: string } | null => {
    const plan = pendingPlan.value;
    const usage = credits.value;

    if (plan === null || usage === null) {
        return null;
    }

    if (plan.ai_credits_per_month === null) {
        return null;
    }

    if (usage.used <= plan.ai_credits_per_month) {
        return null;
    }

    return { date: date.formatDate(usage.renews_at) };
});

const selectPlan = (planId: string): void => {
    if (planForm.processing) {
        return;
    }

    pendingPlanId.value = planId;
    isPlanDialogOpen.value = false;
    isConfirmDialogOpen.value = true;
};

const cancelConfirm = (): void => {
    isConfirmDialogOpen.value = false;
    pendingPlanId.value = null;
    isPlanDialogOpen.value = true;
};

const confirmPlan = (): void => {
    const plan = pendingPlan.value;

    if (plan === null || planForm.processing) {
        return;
    }

    planForm.plan_id = plan.id;
    planForm.interval = selectedInterval.value;
    planForm.post(changePlanRoute.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isConfirmDialogOpen.value = false;
            isPlanDialogOpen.value = false;
        },
    });
};
</script>

<template>
    <Head :title="$t('billing.title')" />

    <SettingsLayout :title="$t('billing.title')">
        <Tabs
            :model-value="tab"
            class="flex flex-col gap-8"
            @update:model-value="selectTab"
        >
            <TabsList variant="line">
                <TabsTrigger
                    v-for="value in billingTabs"
                    :key="value"
                    :value="value"
                    :data-testid="`billing-tab-${value}`"
                >
                    {{ $t(`billing.tabs.${value}`) }}
                </TabsTrigger>
            </TabsList>

            <TabsContent value="plan" class="flex flex-col gap-10">
                <SettingsSection
                    v-if="showsPlanSection"
                    :title="$t('billing.plans.current')"
                >
                    <div
                        class="overflow-hidden rounded-xl border border-border bg-card"
                        data-testid="billing-current-plan"
                    >
                        <div class="flex flex-col gap-5 p-5 sm:p-6">
                            <div
                                class="flex flex-wrap items-start justify-between gap-4"
                            >
                                <div class="flex min-w-0 flex-col gap-3">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p
                                            class="text-lg leading-tight font-emphasis text-foreground"
                                            data-testid="billing-current-plan-name"
                                        >
                                            {{ plan?.name }}
                                        </p>
                                        <Badge
                                            v-if="subscriptionStatus"
                                            :variant="
                                                STATUS_BADGES[
                                                    subscriptionStatus
                                                ]
                                            "
                                            data-testid="billing-current-plan-badge"
                                        >
                                            {{
                                                $t(
                                                    `billing.current_plan.statuses.${subscriptionStatus}`,
                                                )
                                            }}
                                        </Badge>
                                    </div>
                                    <p
                                        v-if="showsPrice"
                                        class="flex items-baseline gap-1 tabular-nums"
                                        data-testid="billing-current-plan-price"
                                    >
                                        <span
                                            class="text-3xl leading-none font-emphasis tracking-tight text-foreground [word-spacing:-0.15em]"
                                        >
                                            {{ currentPrice }}
                                        </span>
                                        <span
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ $t(priceSuffixKey) }}
                                        </span>
                                    </p>
                                    <p
                                        v-if="accountBalance"
                                        class="flex items-center gap-1.5 text-sm text-muted-foreground"
                                        data-testid="billing-account-balance"
                                    >
                                        <IconWallet class="size-4 shrink-0" />
                                        {{
                                            $t('rxdesk.billing.account_balance')
                                        }}
                                        <span
                                            class="font-emphasis text-foreground tabular-nums"
                                            >{{ accountBalance }}</span
                                        >
                                    </p>
                                    <p
                                        v-if="discount"
                                        class="flex items-center gap-1.5 text-sm text-muted-foreground"
                                        data-testid="billing-current-plan-discount"
                                    >
                                        <IconDiscount
                                            class="size-4 shrink-0 text-success-text"
                                        />
                                        {{
                                            $t(discountKey, {
                                                discount: discount.value,
                                                date: date.formatDate(
                                                    discount.ends_at,
                                                ),
                                            })
                                        }}
                                    </p>
                                </div>
                                <Button
                                    v-if="showsChangePlan"
                                    variant="outline"
                                    class="shrink-0"
                                    data-testid="billing-change-plan"
                                    @click="openPlanDialog"
                                >
                                    {{ $t('billing.current_plan.change') }}
                                </Button>
                                <Button
                                    v-else-if="showsSubscribe"
                                    class="shrink-0"
                                    :loading="checkoutForm.processing"
                                    data-testid="billing-subscribe"
                                    @click="subscribe"
                                >
                                    {{ $t('billing.current_plan.subscribe') }}
                                </Button>
                            </div>
                        </div>
                        <div
                            v-if="hasComplimentary"
                            class="flex flex-col gap-1 border-t border-border bg-muted/40 px-5 py-3 text-sm text-muted-foreground sm:px-6"
                            data-testid="billing-current-plan-complimentary"
                        >
                            <p>{{ $t('rxdesk.complimentary.plan_given') }}</p>
                            <p
                                v-if="complimentary?.ends_at"
                                class="text-foreground"
                                data-testid="billing-current-plan-complimentary-until"
                            >
                                {{ complimentaryUntilLine($t) }}
                            </p>
                            <p
                                v-else
                                class="text-foreground"
                                data-testid="billing-current-plan-complimentary-no-end"
                            >
                                {{ $t('rxdesk.complimentary.no_end') }}
                            </p>
                        </div>
                        <div
                            v-if="statusStrip"
                            class="flex flex-wrap items-center justify-between gap-2 border-t border-border bg-muted/40 px-5 py-3 text-sm text-muted-foreground sm:px-6"
                            data-testid="billing-status-strip"
                        >
                            <p
                                class="flex items-center gap-1.5"
                                data-testid="billing-current-plan-status"
                            >
                                <IconSparkles
                                    v-if="subscriptionStatus === 'trial'"
                                    class="size-4 shrink-0 text-primary-text"
                                    data-testid="billing-current-plan-trial-icon"
                                />
                                {{ $t(statusStrip.key, statusStrip.params) }}
                            </p>
                            <Button
                                v-if="subscriptionStatus === 'past_due'"
                                as="a"
                                :href="portal.url()"
                                variant="destructive"
                                size="sm"
                                class="shrink-0"
                                data-testid="billing-update-payment"
                            >
                                {{ $t('billing.past_due_notice.cta') }}
                            </Button>
                        </div>
                    </div>

                    <Dialog
                        v-if="showsChangePlan"
                        v-model:open="isPlanDialogOpen"
                    >
                        <DialogContent
                            class="sm:max-w-3xl"
                            data-testid="billing-plan-dialog"
                        >
                            <DialogHeader>
                                <DialogTitle>
                                    {{ $t('billing.current_plan.change') }}
                                </DialogTitle>
                                <DialogDescription>
                                    {{ $t('billing.plans.description') }}
                                </DialogDescription>
                            </DialogHeader>
                            <PlanPicker
                                :plans="plans"
                                v-model:interval="selectedInterval"
                                :offer="changePlanOffer"
                                :current-plan-id="plan?.id ?? null"
                                :current-interval="currentInterval"
                                :disabled-plan-ids="deniedPlanIds"
                                :processing="planForm.processing"
                                @select="selectPlan"
                            />
                        </DialogContent>
                    </Dialog>

                    <Dialog
                        v-if="showsChangePlan"
                        v-model:open="isConfirmDialogOpen"
                    >
                        <DialogContent
                            class="sm:max-w-md"
                            data-testid="billing-confirm-dialog"
                        >
                            <DialogHeader>
                                <DialogTitle>
                                    {{
                                        $t('rxdesk.billing.confirm.title', {
                                            plan: pendingPlanName,
                                        })
                                    }}
                                </DialogTitle>
                                <DialogDescription>
                                    {{ $t('billing.plans.description') }}
                                </DialogDescription>
                            </DialogHeader>
                            <div class="flex flex-col gap-3 text-sm">
                                <p
                                    v-if="pendingPrice"
                                    class="flex items-baseline gap-1 text-foreground tabular-nums"
                                    data-testid="billing-confirm-price"
                                >
                                    <span
                                        class="text-2xl leading-9 font-emphasis tracking-tight [word-spacing:-0.15em]"
                                        >{{ pendingPrice }}</span
                                    >
                                    <span class="text-sm text-muted-foreground">
                                        {{ $t(pendingPriceSuffixKey) }}
                                    </span>
                                </p>
                                <p
                                    v-if="pendingTimingKey"
                                    class="text-muted-foreground"
                                    data-testid="billing-confirm-timing"
                                >
                                    {{ $t(pendingTimingKey) }}
                                </p>
                                <p
                                    v-if="pendingOffer"
                                    class="text-muted-foreground"
                                    data-testid="billing-confirm-offer"
                                >
                                    {{
                                        $t(
                                            pendingOffer.key,
                                            pendingOffer.params,
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="pendingQuotaWarning"
                                    class="rounded-lg bg-warning/10 px-3 py-2 text-foreground"
                                    data-testid="billing-confirm-quota"
                                >
                                    {{
                                        $t(
                                            'rxdesk.billing.confirm.quota_pause',
                                            pendingQuotaWarning,
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="pendingAiWarning"
                                    class="rounded-lg bg-warning/10 px-3 py-2 text-foreground"
                                    data-testid="billing-confirm-ai"
                                >
                                    {{
                                        $t(
                                            'rxdesk.billing.confirm.ai_pause',
                                            pendingAiWarning,
                                        )
                                    }}
                                </p>
                            </div>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    data-testid="billing-confirm-cancel"
                                    @click="cancelConfirm"
                                >
                                    {{ $t('common.cancel') }}
                                </Button>
                                <Button
                                    :loading="planForm.processing"
                                    data-testid="billing-confirm-change"
                                    @click="confirmPlan"
                                >
                                    {{ $t('billing.current_plan.change') }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </SettingsSection>

                <div id="usage" class="scroll-mt-24">
                    <SettingsSection :title="$t('rxdesk.billing.usage.title')">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div
                                v-if="storage"
                                class="flex flex-col gap-2 rounded-xl border border-border bg-card p-4"
                                data-testid="billing-usage-storage"
                            >
                                <span
                                    class="text-sm font-emphasis text-foreground"
                                    >{{ $t('rxdesk.storage_bar.label') }}</span
                                >
                                <span
                                    class="text-sm text-muted-foreground"
                                    data-testid="billing-usage-storage-line"
                                    >{{ storageLine($t) }}</span
                                >
                                <Progress
                                    :model-value="storage.percent"
                                    class="h-1.5"
                                />
                            </div>
                            <div
                                v-if="credits"
                                class="flex flex-col gap-2 rounded-xl border border-border bg-card p-4"
                                data-testid="billing-usage-ai"
                            >
                                <span
                                    class="text-sm font-emphasis text-foreground"
                                    >{{ $t('rxdesk.ai_credits.label') }}</span
                                >
                                <span
                                    class="text-sm text-muted-foreground"
                                    data-testid="billing-usage-ai-line"
                                    >{{ aiLine($t) }}</span
                                >
                                <Progress
                                    :model-value="credits.percent"
                                    class="h-1.5"
                                />
                            </div>
                        </div>
                    </SettingsSection>
                </div>

                <SettingsSection
                    v-if="showsSubscriptionSection"
                    :title="$t('billing.subscription.title')"
                    :description="$t('billing.subscription.description')"
                >
                    <template #actions>
                        <Button
                            as="a"
                            variant="outline"
                            :href="portal.url()"
                            data-testid="billing-manage-stripe"
                        >
                            {{ $t('billing.subscription.manage_stripe') }}
                        </Button>
                    </template>

                    <ul
                        v-if="paymentMethods.length > 0"
                        class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
                        data-testid="billing-payment-methods"
                    >
                        <li
                            v-for="paymentMethod in paymentMethods"
                            :key="paymentMethod.id"
                            class="flex items-center gap-3 px-4 py-3"
                            :data-testid="`billing-card-${paymentMethod.id}`"
                        >
                            <img
                                :src="cardBrand(paymentMethod.brand).logo"
                                alt=""
                                class="h-7 w-auto shrink-0 rounded-[5px] shadow-xs"
                                :data-testid="`billing-card-logo-${paymentMethod.id}`"
                            />
                            <div class="flex min-w-0 flex-1 flex-col">
                                <p
                                    class="flex flex-wrap items-center gap-1.5 text-[13px] leading-5 font-emphasis text-foreground"
                                    data-testid="billing-card-label"
                                >
                                    {{ cardBrand(paymentMethod.brand).name }}
                                    <span class="tabular-nums"
                                        >•••• {{ paymentMethod.last4 }}</span
                                    >
                                    <Badge
                                        v-if="paymentMethod.is_default"
                                        variant="secondary"
                                        data-testid="billing-card-default"
                                    >
                                        {{ $t('billing.subscription.default') }}
                                    </Badge>
                                </p>
                                <p
                                    class="text-xs leading-4 text-muted-foreground tabular-nums"
                                >
                                    {{
                                        $t('billing.subscription.expires_on', {
                                            month: paymentMethod.exp_month
                                                .toString()
                                                .padStart(2, '0'),
                                            year: paymentMethod.exp_year.toString(),
                                        })
                                    }}
                                </p>
                            </div>
                        </li>
                    </ul>
                    <div
                        v-else
                        class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3"
                    >
                        <span
                            class="inline-flex h-7 w-11 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground"
                        >
                            <IconCreditCard class="size-5" />
                        </span>
                        <p class="text-sm text-muted-foreground">
                            {{ $t('billing.subscription.no_payment_method') }}
                        </p>
                    </div>
                </SettingsSection>
            </TabsContent>

            <TabsContent value="invoices">
                <div
                    v-if="invoices.data.length === 0"
                    class="rounded-xl border border-border bg-card"
                >
                    <EmptyState
                        :icon="IconFileText"
                        :title="$t('billing.invoices.empty_title')"
                        :description="$t('billing.invoices.empty_description')"
                        data-testid="billing-invoices-empty"
                    />
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <InfiniteScroll
                        data="invoices"
                        items-element="#billing-invoices-body"
                        preserve-url
                    >
                        <div class="overflow-x-auto">
                            <Table data-testid="billing-invoices">
                                <TableHeader>
                                    <TableRow class="hover:bg-transparent">
                                        <TableHead
                                            class="h-11 border-r-0 px-4 sm:px-5"
                                        >
                                            {{
                                                $t(
                                                    'billing.invoices.columns.date',
                                                )
                                            }}
                                        </TableHead>
                                        <TableHead class="h-11 border-r-0 px-4">
                                            {{
                                                $t(
                                                    'billing.invoices.columns.amount',
                                                )
                                            }}
                                        </TableHead>
                                        <TableHead class="h-11 border-r-0 px-4">
                                            {{
                                                $t(
                                                    'billing.invoices.columns.status',
                                                )
                                            }}
                                        </TableHead>
                                        <TableHead
                                            class="h-11 w-14 border-r-0 px-4"
                                        >
                                            <span class="sr-only">
                                                {{
                                                    $t(
                                                        'billing.invoices.download',
                                                    )
                                                }}
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody id="billing-invoices-body">
                                    <TableRow
                                        v-for="invoice in invoices.data"
                                        :key="invoice.id"
                                        class="last:border-b-0"
                                        :data-testid="`billing-invoice-${invoice.id}`"
                                    >
                                        <TableCell
                                            class="border-r-0 px-4 py-3 font-emphasis text-foreground sm:px-5"
                                        >
                                            {{ date.formatDate(invoice.date) }}
                                        </TableCell>
                                        <TableCell
                                            class="border-r-0 px-4 py-3 tabular-nums"
                                        >
                                            {{ invoice.total }}
                                        </TableCell>
                                        <TableCell class="border-r-0 px-4 py-3">
                                            <Badge variant="success">
                                                {{
                                                    $t('billing.invoices.paid')
                                                }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell
                                            class="border-r-0 px-2 py-2 text-right"
                                        >
                                            <Button
                                                v-if="invoice.invoice_pdf"
                                                variant="ghost"
                                                size="icon"
                                                as="a"
                                                :href="invoice.invoice_pdf"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-muted-foreground"
                                                :aria-label="
                                                    $t(
                                                        'billing.invoices.download',
                                                    )
                                                "
                                                :data-testid="`billing-invoice-download-${invoice.id}`"
                                            >
                                                <IconDownload class="size-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <template #next="{ loading }">
                            <TableLoadMore v-if="loading" />
                        </template>
                    </InfiniteScroll>
                </div>
            </TabsContent>
        </Tabs>
    </SettingsLayout>
</template>
