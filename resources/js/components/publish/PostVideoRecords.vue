<script setup lang="ts">
import { IconVideoOff } from '@tabler/icons-vue';
import { computed } from 'vue';

import { filled } from '@/lib/helpers';
import type { PostCard } from '@/types/publish';
import type { VideoRecord, VideoRecordOutcome } from '@/types/video-policy';

/**
 * The status line that stands in for a video the Video policy removed from
 * the server. Only Delete writes records so far; Backup adds its outcomes.
 */
const SHOWN_OUTCOMES: VideoRecordOutcome[] = ['deleted'];

const props = defineProps<{
    post: PostCard;
    testKey: string;
}>();

const deletedVideos = computed<VideoRecord[]>(() => {
    const records = props.post.video_records;

    if (filled(records)) {
        return records.filter((record) =>
            SHOWN_OUTCOMES.includes(record.outcome),
        );
    }

    return [];
});

const hasDeletedVideos = computed(() => filled(deletedVideos.value));
</script>

<template>
    <ul
        v-if="hasDeletedVideos"
        class="flex flex-col gap-1"
        :data-testid="`post-details-video-records-${testKey}`"
    >
        <li
            v-for="record in deletedVideos"
            :key="record.id"
            class="flex min-w-0 items-center gap-2 text-sm text-muted-foreground"
        >
            <IconVideoOff aria-hidden="true" class="size-4 shrink-0" />
            <span class="shrink-0">{{
                $t('rxdesk.video_policy.record.deleted')
            }}</span>
            <span v-if="filled(record.original_filename)" class="truncate">{{
                record.original_filename
            }}</span>
        </li>
    </ul>
</template>
