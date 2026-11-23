import type { OfferPriceSlot, OfferTrialSlot } from '@/types/offer';

export const BILLING_INTERVALS = ['monthly', 'yearly'] as const;

export type BillingInterval = (typeof BILLING_INTERVALS)[number];

export const DEFAULT_BILLING_INTERVAL: BillingInterval = 'monthly';

export interface PlanOption {
    id: string;
    slug: string;
    name: string;
    workspace_limit: number | null;
    sort: number;
    quota_bytes: number | null;
    quota_label: string | null;
    ai_credits_per_month: number | null;
    ai_credits_label: string | null;
    monthly_amount: number | null;
    yearly_amount: number | null;
    monthly_price: string | null;
    yearly_price: string | null;
    yearly_monthly_price: string | null;
}

export interface WelcomePlan extends PlanOption {
    quota_label: string | null;
    ai_credits_label: string | null;
    offer: Record<BillingInterval, OfferPriceSlot | null>;
    trial: Record<BillingInterval, OfferTrialSlot | null>;
}

export interface WelcomePlanTrial {
    days: number;
    first_charge_at: string;
}

export interface WelcomePlanSelection {
    plan: string;
    interval: BillingInterval;
}

export interface WelcomePlanRetry {
    plan: string | null;
    update_card_url: string;
}

export interface WelcomePlanLapsedDeletion {
    date: string;
}

export const deniedPlanIdsFor = (
    plans: PlanOption[],
    workspaceCount: number,
): string[] =>
    plans
        .filter(
            (plan) =>
                plan.workspace_limit !== null &&
                workspaceCount > plan.workspace_limit,
        )
        .map((plan) => plan.id);

export const PLAN_CHANGE_ACTIONS = ['upgrade', 'downgrade', 'select'] as const;

export type PlanChangeAction = (typeof PLAN_CHANGE_ACTIONS)[number];

export const PLAN_CHANGE_LABELS = {
    upgrade: 'billing.plans.upgrade',
    downgrade: 'billing.plans.downgrade',
    select: 'billing.plans.select',
} as const satisfies Record<
    PlanChangeAction,
    `billing.plans.${PlanChangeAction}`
>;

export const planChangeAction = (
    current: PlanOption | null | undefined,
    target: PlanOption,
): PlanChangeAction => {
    if (current === null || current === undefined || current.id === target.id) {
        return 'select';
    }

    if (target.sort > current.sort) {
        return 'upgrade';
    }

    if (target.sort < current.sort) {
        return 'downgrade';
    }

    return 'select';
};

export interface AuthPlan {
    id: string;
    slug: string;
    name: string;
    interval: BillingInterval;
}

export const PLAN_CHANGE_DIRECTIONS = ['upgrade', 'downgrade', 'same'] as const;

export type PlanChangeDirection = (typeof PLAN_CHANGE_DIRECTIONS)[number];

/**
 * Which way a change moves the bill: a dearer Plan by sort, or the same Plan
 * switching monthly → yearly, is an upgrade; the reverse is a downgrade. The
 * change-plan dialog says what happens to the money from this.
 */
export const planChangeDirection = (
    current: PlanOption | null,
    currentInterval: BillingInterval,
    target: PlanOption,
    targetInterval: BillingInterval,
): PlanChangeDirection => {
    if (current === null) {
        return 'same';
    }

    if (current.id === target.id) {
        if (targetInterval === currentInterval) {
            return 'same';
        }

        if (targetInterval === 'yearly') {
            return 'upgrade';
        }

        return 'downgrade';
    }

    if (target.sort > current.sort) {
        return 'upgrade';
    }

    if (target.sort < current.sort) {
        return 'downgrade';
    }

    return 'same';
};

export interface Features {
    workspaceLimit: number | null;
}
