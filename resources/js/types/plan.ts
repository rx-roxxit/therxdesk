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
    ai_credits_per_month: number | null;
    monthly_amount: number | null;
    yearly_amount: number | null;
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

export interface Features {
    workspaceLimit: number | null;
}
