<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconCloudCheck,
    IconVideoOff,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, type Component } from 'vue';
import { toast } from 'vue-sonner';

import { store as retryVideoBackup } from '@/actions/App/Http/Controllers/App/VideoBackupRetryController';
import { Button } from '@/components/ui/button';
import { useWorkspaceAbilities } from '@/composables/useWorkspaceAbilities';
import { filled } from '@/lib/helpers';
import type { PostCard } from '@/types/publish';
import type { VideoRecord, VideoRecordOutcome } from '@/types/video-policy';

/**
 * The status line that stands in for a video the Video policy removed from
 * the server (deleted, or backed up with a link to its Drive copy), or kept
 * because its backup failed, which the Owner can retry.
 */
const SHOWN_OUTCOMES: VideoRecordOutcome[] = [
    'deleted',
    'backed_up',
    'backup_failed',
];

type Translate = (key: string) => string;

const props = defineProps<{
    post: PostCard;
    testKey: string;
}>();

const { isOwner } = useWorkspaceAbilities();

const retriedPostIds = ref<string[]>([]);

const retrying = ref(false);

const wasRetried = computed(() => retriedPostIds.value.includes(props.post.id));

const removedVideos = computed<VideoRecord[]>(() => {
    const records = props.post.video_records;

    if (!filled(records)) {
        return [];
    }

    return records.filter((record) => {
        if (!SHOWN_OUTCOMES.includes(record.outcome)) {
            return false;
        }

        return !(record.outcome === 'backup_failed' && wasRetried.value);
    });
});

const hasRemovedVideos = computed(() => filled(removedVideos.value));

const hasFailedBackup = computed(() =>
    removedVideos.value.some((record) => record.outcome === 'backup_failed'),
);

const canRetry = computed(() => isOwner.value && hasFailedBackup.value);

const retryBackup = (): void => {
    router.post(
        retryVideoBackup.url(props.post.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                retrying.value = true;
            },
            onSuccess: () => {
                retriedPostIds.value = [...retriedPostIds.value, props.post.id];
                toast.success(trans('rxdesk.video_backup.retrying'));
            },
            onFinish: () => {
                retrying.value = false;
            },
        },
    );
};

const recordLabel = (record: VideoRecord, translate: Translate): string => {
    if (record.outcome === 'backed_up') {
        return translate('rxdesk.video_policy.record.backed_up');
    }

    if (record.outcome === 'backup_failed') {
        return translate('rxdesk.video_backup.failed');
    }

    return translate('rxdesk.video_policy.record.deleted');
};

const recordIcon = (record: VideoRecord): Component => {
    if (record.outcome === 'backed_up') {
        return IconCloudCheck;
    }

    if (record.outcome === 'backup_failed') {
        return IconAlertTriangle;
    }

    return IconVideoOff;
};
</script>

<template>
    <div
        v-if="hasRemovedVideos"
        class="flex flex-col gap-2"
        :data-testid="`post-details-video-records-${testKey}`"
    >
        <ul class="flex flex-col gap-1">
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
                <span
                    v-if="filled(record.original_filename)"
                    class="truncate"
                    >{{ record.original_filename }}</span
                >
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
        <div v-if="canRetry">
            <Button
                size="sm"
                variant="outline"
                :loading="retrying"
                :data-testid="`post-details-video-backup-retry-${testKey}`"
                @click="retryBackup"
            >
                {{ $t('rxdesk.video_backup.retry') }}
            </Button>
        </div>
    </div>
</template>
