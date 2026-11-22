import type { BillingInterval } from '@/types/plan';

/**
 * One covered Plan × interval: the Offer's badge, the struck regular price,
 * the discounted price and the Offer's line. Every amount is formatted by the
 * server.
 */
export interface OfferPriceSlot {
    badge: string;
    regular: string;
    discounted: string;
    regular_per_month: string;
    discounted_per_month: string;
    line: string;
}

export interface OfferTrialSlot {
    days: number;
    first_charge_at: string;
}

/**
 * The Offer the plan step shows: its campaign name is data, its copy comes
 * from the server's templates.
 */
export interface Offer {
    slug: string;
    name: string;
    reach: 'general' | 'link';
    show_banner: boolean;
    discount_label: string | null;
    lines: Record<BillingInterval, string>;
    trial_days: number | null;
}

/**
 * The Offer a current subscription was bought with, priced for the
 * change-plan cards. Keyed by Plan id.
 */
export interface ChangePlanOffer {
    name: string;
    discount_label: string | null;
    plans: Record<string, Record<BillingInterval, OfferPriceSlot | null>>;
}
