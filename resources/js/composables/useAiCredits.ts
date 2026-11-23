import { usePage } from '@inertiajs/vue3';
import { readonly, shallowRef, watch } from 'vue';

import type { AiCredits, SharedData } from '@/types';

/** The refusal code when the viewer is the Account Owner; a member gets its own message instead. */
export const AI_CREDITS_EXHAUSTED_OWNER = 'ai_credits_exhausted_owner';

/**
 * The Account's AI credit balance, shared by every AI surface: seeded from the
 * page's `aiCredits` prop and written by each AI response, so the low-credits
 * note updates everywhere without a page visit.
 */
const credits = shallowRef<AiCredits | null>(null);

const readonlyCredits = readonly(credits);

/** The page prop last read, so a surface mounting later seeds itself without overwriting a fresher response. */
let lastPageValue: AiCredits | null | undefined;

const apply = (payload: AiCredits | null): void => {
    credits.value = payload;
};

export const useAiCredits = () => {
    const page = usePage<SharedData>();

    watch(
        (): AiCredits | null => page.props.aiCredits ?? null,
        (value) => {
            if (value === lastPageValue) {
                return;
            }

            lastPageValue = value;
            apply(value);
        },
        { immediate: true },
    );

    return { credits: readonlyCredits, apply };
};
