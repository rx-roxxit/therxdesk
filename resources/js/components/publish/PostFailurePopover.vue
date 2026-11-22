<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

import PlatformBrandIcon from '@/components/PlatformBrandIcon.vue';
import PostStatusBadge from '@/components/publish/PostStatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { getPlatformLabel } from '@/composables/usePlatformLogo';
import { useWorkspaceAbilities } from '@/composables/useWorkspaceAbilities';
import date from '@/date';
import { filled } from '@/lib/helpers';
import { retry as retryPost } from '@/routes/app/posts';
import type { PublishStatusValue } from '@/types/post';
import { PublishStatus } from '@/types/post';
import type { PostCard, PublishBlockReason } from '@/types/publish';

const props = defineProps<{
    post: PostCard;
    testKey: string;
    attemptedAt: string | null;
    timezone: string;
}>();

const CATEGORIES = [
    'media_format',
    'rate_limit',
    'permission',
    'content_policy',
    'server_error',
    'platform_unavailable',
    'timeout',
    'token_expired',
    'job_failed',
];

const FAILURE_STATUSES: readonly PublishStatusValue[] = [
    PublishStatus.Failed,
    PublishStatus.Rejected,
];

const { canPublishDirectly } = useWorkspaceAbilities();

const blocked = computed<PublishBlockReason | null>(() => {
    const post = props.post;

    if (!filled(post.publish_block_reason)) {
        return null;
    }

    if (post.publish_status !== PublishStatus.Failed) {
        return null;
    }

    return post.publish_block_reason;
});

const headingKey = computed(() => {
    if (blocked.value !== null) {
        return 'rxdesk.publishing_block.title';
    }

    return 'posts.publish.failure.title';
});

const blockReasonKey = computed(() => {
    if (blocked.value === null) {
        return 'posts.publish.failure.generic';
    }

    return `rxdesk.publishing_block.${blocked.value}`;
});

const failure = computed(() => {
    const post = props.post;

    if (!post.platform || !FAILURE_STATUSES.includes(post.publish_status)) {
        return null;
    }

    const category = post.failure?.category ?? '';
    const at = post.failure?.failed_at ?? props.attemptedAt;
    let reasonKey = 'posts.publish.failure.generic';

    if (CATEGORIES.includes(category)) {
        reasonKey = `posts.publish.failure.categories.${category}`;
    }

    return {
        platform: post.platform,
        account: post.social_account?.display_label ?? null,
        reasonKey,
        details: post.error_message?.trim() || null,
        at: at ? date.formatDateTimeInTimezone(at, props.timezone) : null,
    };
});

const toastFirstError = (errors: Record<string, string>): void => {
    const message = Object.values(errors)[0];

    if (message) {
        toast.error(message);
    }
};

const retry = (): void => {
    router.post(
        retryPost.url(props.post.id),
        {},
        {
            preserveScroll: true,
            onError: toastFirstError,
        },
    );
};
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <button
                type="button"
                class="rounded-full focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                :aria-label="$t('posts.publish.failure.title')"
                :data-testid="`post-status-${testKey}`"
            >
                <PostStatusBadge :status="post.status" class="cursor-pointer" />
            </button>
        </PopoverTrigger>
        <PopoverContent
            align="start"
            class="flex w-80 flex-col gap-4"
            :data-testid="`post-failure-${testKey}`"
        >
            <p class="text-sm font-emphasis text-foreground">
                {{ $t(headingKey) }}
            </p>
            <div
                v-if="blocked !== null"
                class="flex flex-col gap-2"
                :data-testid="`post-block-reason-${testKey}`"
            >
                <p
                    class="text-sm text-foreground"
                    :data-testid="`post-failure-reason-${testKey}`"
                >
                    {{ $t(blockReasonKey) }}
                </p>
                <Button
                    v-if="canPublishDirectly"
                    type="button"
                    size="sm"
                    class="self-start"
                    :data-testid="`post-retry-${testKey}`"
                    @click="retry"
                >
                    {{ $t('rxdesk.publishing_block.retry') }}
                </Button>
            </div>
            <p
                v-else-if="!failure"
                class="text-sm text-foreground"
                :data-testid="`post-failure-reason-${testKey}`"
            >
                {{ $t('posts.publish.failure.generic') }}
            </p>
            <div v-else class="flex flex-col gap-2">
                <p
                    class="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <PlatformBrandIcon :platform="failure.platform" />
                    <span class="truncate">
                        {{
                            failure.account ??
                            getPlatformLabel(failure.platform)
                        }}
                    </span>
                    <template v-if="failure.at">
                        <span aria-hidden="true">·</span>
                        <time class="shrink-0">{{ failure.at }}</time>
                    </template>
                </p>
                <p
                    class="text-sm text-foreground"
                    :data-testid="`post-failure-reason-${testKey}`"
                >
                    {{ $t(failure.reasonKey) }}
                </p>
                <div v-if="failure.details" class="flex flex-col gap-1">
                    <p class="text-xs text-muted-foreground">
                        {{ $t('posts.publish.failure.details') }}
                    </p>
                    <p
                        class="rounded-md bg-secondary px-3 py-2 text-xs break-words text-foreground"
                        :data-testid="`post-failure-details-${testKey}`"
                    >
                        {{ failure.details }}
                    </p>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
