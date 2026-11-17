<script setup lang="ts">
import { IconCloudCheck, IconVideoOff } from '@tabler/icons-vue';
import { computed, type Component } from 'vue';

import { filled } from '@/lib/helpers';
import type { PostCard } from '@/types/publish';
import type { VideoRecord, VideoRecordOutcome } from '@/types/video-policy';

/**
 * The status line that stands in for a video the Video policy removed from
 * the server: deleted, or backed up with a link to its Drive copy.
 */
const SHOWN_OUTCOMES: VideoRecordOutcome[] = ['deleted', 'backed_up'];

type Translate = (key: string) => string;

const props = defineProps<{
    post: PostCard;
    testKey: string;
}>();

const removedVideos = computed<VideoRecord[]>(() => {
    const records = props.post.video_records;

    if (filled(records)) {
        return records.filter((record) =>
            SHOWN_OUTCOMES.includes(record.outcome),
        );
    }

    return [];
});

const hasRemovedVideos = computed(() => filled(removedVideos.value));

const recordLabel = (record: VideoRecord, translate: Translate): string => {
    if (record.outcome === 'backed_up') {
        return translate('rxdesk.video_policy.record.backed_up');
    }

    return translate('rxdesk.video_policy.record.deleted');
};

const recordIcon = (record: VideoRecord): Component => {
    if (record.outcome === 'backed_up') {
        return IconCloudCheck;
    }

    return IconVideoOff;
};
</script>

<template>
    <ul
        v-if="hasRemovedVideos"
        class="flex flex-col gap-1"
        :data-testid="`post-details-video-records-${testKey}`"
    >
        <li
            v-for="record in removedVideos"
            :key="record.id"
            class="flex min-w-0 items-center gap-2 text-sm text-muted-foreground"
        >
            <component
                :is="recordIcon(record)"
                aria-hidden="true"
                class="size-4 shrink-0"
            />
            <span class="shrink-0">{{ recordLabel(record, $t) }}</span>
            <span v-if="filled(record.original_filename)" class="truncate">{{
                record.original_filename
            }}</span>
            <a
                v-if="filled(record.drive_link)"
                :href="record.drive_link"
                target="_blank"
                rel="noopener noreferrer"
                class="shrink-0 text-foreground underline underline-offset-2 hover:no-underline"
                :data-testid="`post-details-video-drive-link-${record.id}`"
                >{{ $t('rxdesk.video_policy.record.open_in_drive') }}</a
            >
        </li>
    </ul>
</template>
