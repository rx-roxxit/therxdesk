<script setup lang="ts">
import { IconPhotoOff } from '@tabler/icons-vue';
import { computed } from 'vue';

import { filled } from '@/lib/helpers';
import type { PostCard } from '@/types/publish';
import { CoverOutcomeState } from '@/types/video-policy';

/**
 * The one line that says a network refused a published video's Cover. The
 * video stays published; the Owner sets the Cover in the network's own app.
 */
const props = defineProps<{
    post: PostCard;
    testKey: string;
}>();

const coverFailed = computed<boolean>(() => {
    const outcome = props.post.cover_outcome;

    if (!filled(outcome)) {
        return false;
    }

    return outcome.outcome === CoverOutcomeState.Failed;
});
</script>

<template>
    <p
        v-if="coverFailed"
        class="flex min-w-0 items-center gap-2 text-sm text-muted-foreground"
        :data-testid="`post-details-cover-failed-${testKey}`"
    >
        <IconPhotoOff aria-hidden="true" class="size-4 shrink-0" />
        <span>{{ $t('rxdesk.cover.outcome.failed') }}</span>
    </p>
</template>
