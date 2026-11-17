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
type ShownOutcome = Exclude<VideoRecordOutcome, 'pending'>;

const SHOWN_OUTCOMES: ShownOutcome[] = [
    'deleted',
    'backed_up',
    'backup_failed',
];

/** What each shown outcome reads as, and its icon. */
const OUTCOME_DISPLAY: Record<
    ShownOutcome,
    { labelKey: string; icon: Component }
> = {
    deleted: {
        labelKey: 'rxdesk.video_policy.record.deleted',
        icon: IconVideoOff,
    },
    backed_up: {
        labelKey: 'rxdesk.video_policy.record.backed_up',
        icon: IconCloudCheck,
    },
    backup_failed: {
        labelKey: 'rxdesk.video_backup.failed',
        icon: IconAlertTriangle,
    },
};

const props = defineProps<{
    post: PostCard;
    testKey: string;
}>();

const { isOwner } = useWorkspaceAbilities();

const retriedPostIds = ref<string[]>([]);

const retrying = ref(false);

const wasRetried = computed(() => retriedPostIds.value.includes(props.post.id));

/** A failed backup the Owner just retried no longer shows; every other shown outcome does. */
const isShown = (record: VideoRecord): boolean => {
    if (!SHOWN_OUTCOMES.includes(record.outcome as ShownOutcome)) {
        return false;
    }

    if (record.outcome !== 'backup_failed') {
        return true;
    }

    return !wasRetried.value;
};

const removedVideos = computed<VideoRecord[]>(() => {
    const records = props.post.video_records;

    if (!filled(records)) {
        return [];
    }

    return records.filter(isShown);
});

const hasRemovedVideos = computed(() => filled(removedVideos.value));

const hasFailedBackup = computed(() =>
    removedVideos.value.some((record) => record.outcome === 'backup_failed'),
);

const canRetry = computed(() => {
    if (!isOwner.value) {
        return false;
    }

    return hasFailedBackup.value;
});

const retryFailedMessage = (errors: Record<string, string>): string => {
    if (filled(errors.post)) {
        return errors.post;
    }

    return trans('rxdesk.video_backup.errors.retry_failed');
};

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
            onError: (errors) => {
                toast.error(retryFailedMessage(errors));
            },
            onFinish: () => {
                retrying.value = false;
            },
        },
    );
};

const recordDisplay = (
    record: VideoRecord,
): { labelKey: string; icon: Component } =>
    OUTCOME_DISPLAY[record.outcome as ShownOutcome];
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
                    :is="recordDisplay(record).icon"
                    aria-hidden="true"
                    class="size-4 shrink-0"
                />
                <span class="shrink-0">{{
                    $t(recordDisplay(record).labelKey)
                }}</span>
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
