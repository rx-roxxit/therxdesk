import { isString } from '@/lib/helpers';

export const extractErrorMessage = (error: unknown): string | null => {
    const data = (error as { response?: { data?: string } })?.response?.data;
    if (typeof data !== 'string') return null;
    try {
        return JSON.parse(data)?.message ?? null;
    } catch {
        return null;
    }
};

/**
 * The stable refusal code a failed request carries in its JSON body
 * (`ai_credits_exhausted_owner`), read as defensively as its message: a failed
 * request may carry anything.
 */
export const extractErrorCode = (error: unknown): string | null => {
    const data = (error as { response?: { data?: unknown } })?.response?.data;

    if (!isString(data)) {
        return null;
    }

    try {
        const code = JSON.parse(data)?.code;

        return isString(code) ? code : null;
    } catch {
        return null;
    }
};
