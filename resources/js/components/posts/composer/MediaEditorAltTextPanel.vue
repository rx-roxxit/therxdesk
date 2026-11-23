<script setup lang="ts">
import { Link, useHttp, usePage } from '@inertiajs/vue3';
import { IconSparkles } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import AiCreditsNote from '@/components/ai/AiCreditsNote.vue';
import { Button } from '@/components/ui/button';
import {
    AI_CREDITS_EXHAUSTED_OWNER,
    useAiCredits,
} from '@/composables/useAiCredits';
import { extractErrorCode, extractErrorMessage } from '@/lib/httpError';
import type { MediaEdit } from '@/lib/mediaEditor';
import { index as billingIndex } from '@/routes/app/billing';
import { altText as generateAltTextRoute } from '@/routes/app/posts/ai';
import type { AiCredits } from '@/types';

const props = defineProps<{
    mediaId: string;
}>();

const edit = defineModel<MediaEdit>('edit', { required: true });

const page = usePage();
const aiEnabled = computed(() => page.props.aiEnabled === true);
const http = useHttp({ media_id: '' });
const generating = ref(false);
const error = ref<string | null>(null);
const errorCode = ref<string | null>(null);

const { apply } = useAiCredits();

/** Only the Account Owner can change the plan, so only their refusal carries the link. */
const showsChangePlan = computed(
    (): boolean => errorCode.value === AI_CREDITS_EXHAUSTED_OWNER,
);

const generate = async (): Promise<void> => {
    const target = edit.value;
    generating.value = true;
    error.value = null;
    errorCode.value = null;
    http.media_id = props.mediaId;
    try {
        const result = (await http.post(generateAltTextRoute.url())) as {
            alt_text: string;
            ai_credits: AiCredits | null;
        };
        apply(result.ai_credits);
        target.altText = result.alt_text;
    } catch (exception) {
        error.value =
            extractErrorMessage(exception) ??
            trans('posts.composer.media_editor.alt_generate_error');
        errorCode.value = extractErrorCode(exception);
    } finally {
        generating.value = false;
    }
};
</script>

<template>
    <div class="space-y-3">
        <div class="space-y-1">
            <label
                for="media-editor-alt-text"
                class="block text-sm font-medium"
            >
                {{ $t('posts.composer.media_editor.alt_heading') }}
            </label>
            <p class="text-xs text-muted-foreground">
                {{ $t('posts.composer.media_editor.alt_hint') }}
            </p>
        </div>
        <textarea
            id="media-editor-alt-text"
            v-model="edit.altText"
            data-testid="media-editor-alt-text"
            rows="5"
            class="w-full rounded-lg border border-border bg-background p-3 text-sm"
            :placeholder="$t('posts.composer.media_editor.alt_placeholder')"
        />
        <Button
            v-if="aiEnabled"
            type="button"
            variant="outline"
            class="w-full"
            data-testid="media-editor-alt-generate"
            :disabled="generating"
            @click="generate"
        >
            <IconSparkles class="size-4" />
            {{ $t('posts.composer.media_editor.alt_generate') }}
        </Button>
        <AiCreditsNote v-if="aiEnabled" />
        <div
            v-if="error"
            role="alert"
            class="space-y-1 text-sm text-destructive"
            data-testid="media-editor-alt-error"
        >
            <p>{{ error }}</p>
            <Link
                v-if="showsChangePlan"
                :href="billingIndex.url()"
                class="font-medium underline underline-offset-2"
                data-testid="media-editor-alt-change-plan"
            >
                {{ $t('rxdesk.ai_credits.change_plan') }}
            </Link>
        </div>
    </div>
</template>
