<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Progress } from '@/components/ui/progress';
import { filled } from '@/lib/helpers';
import { preferences } from '@/routes/app/settings';
import type { StorageSummary } from '@/types';

const FULL_PERCENT = 100;
const VIDEO_POLICY_ANCHOR = '#video-policy';
const PROGRESS_CLASS = 'h-1.5';
const FULL_PROGRESS_CLASS = `${PROGRESS_CLASS} bg-destructive/20 [&>[data-slot=progress-indicator]]:bg-destructive`;

const page = usePage();
const storage = computed<StorageSummary | null>(() => {
    const shared = page.props.storage as StorageSummary | null | undefined;

    if (filled(shared)) {
        return shared;
    }

    return null;
});
const preferencesUrl = computed<string>(
    () => `${preferences.url()}${VIDEO_POLICY_ANCHOR}`,
);
const progressClass = computed<string>(() => {
    if (!filled(storage.value)) {
        return PROGRESS_CLASS;
    }

    if (storage.value.percent >= FULL_PERCENT) {
        return FULL_PROGRESS_CLASS;
    }

    return PROGRESS_CLASS;
});
</script>

<template>
    <DropdownMenuItem v-if="storage" :as-child="true" class="h-auto p-0">
        <Link
            :href="preferencesUrl"
            class="flex w-full cursor-pointer flex-col items-stretch gap-1.5 rounded-md px-2 py-1.5"
            data-testid="sidebar-menu-storage"
        >
            <span class="flex items-baseline justify-between gap-2 text-xs">
                <span class="font-medium text-foreground">{{
                    $t('rxdesk.storage_bar.label')
                }}</span>
                <span
                    class="truncate text-muted-foreground"
                    data-testid="sidebar-menu-storage-usage"
                >
                    {{
                        $t('rxdesk.storage_bar.usage', {
                            used: storage.used_label,
                            limit: storage.limit_label,
                        })
                    }}
                </span>
            </span>
            <Progress
                :model-value="storage.percent"
                :class="progressClass"
                data-testid="sidebar-menu-storage-progress"
            />
        </Link>
    </DropdownMenuItem>
</template>
