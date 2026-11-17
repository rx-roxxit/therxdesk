<script setup lang="ts">
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    coverImageFileError,
    coverImageMegabytes,
    type CoverImageLimits,
} from '@/lib/coverImage';
import { filled } from '@/lib/helpers';
import { formatCoverOffset, type MediaEdit } from '@/lib/mediaEditor';
import { rangeFill } from '@/lib/rangeFill';
import { storeChunked as mediaStoreChunked } from '@/routes/app/media';
import { ChunkedUploadError, uploadChunked } from '@/utils/chunkedUpload';

const COVER_MODES = ['frame', 'image'] as const;

type CoverMode = (typeof COVER_MODES)[number];

const props = withDefaults(
    defineProps<{
        duration: number | null;
        /** Whether this video's networks take a picked frame. */
        framePicker?: boolean;
        /** What a Cover image may be, or null when no network takes one here. */
        coverImageLimits?: CoverImageLimits | null;
    }>(),
    { framePicker: true, coverImageLimits: null },
);

const edit = defineModel<MediaEdit>('edit', { required: true });

const maxSeconds = computed(() =>
    Math.max(0, Math.floor((props.duration ?? 0) * 10) / 10),
);
const seconds = computed({
    get: () => (edit.value.coverOffsetMs ?? 0) / 1000,
    set: (value: number) => {
        edit.value.coverOffsetMs = Math.round(value * 1000);
        edit.value.coverImage = null;
    },
});

const initialMode = (): CoverMode => {
    if (filled(edit.value.coverImage)) {
        return 'image';
    }

    if (!props.framePicker) {
        return 'image';
    }

    return 'frame';
};

const mode = ref<CoverMode>(initialMode());
const uploading = ref(false);
const errorKey = ref<string | null>(null);
const serverError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const offersCoverImage = computed(() => filled(props.coverImageLimits));
const offersBoth = computed(() => {
    if (!props.framePicker) {
        return false;
    }

    return offersCoverImage.value;
});
const showsFramePicker = computed(() => {
    if (!props.framePicker) {
        return false;
    }

    return mode.value === 'frame';
});
const showsCoverImage = computed(() => {
    if (!offersCoverImage.value) {
        return false;
    }

    return mode.value === 'image';
});
const hasCoverImage = computed(() => filled(edit.value.coverImage));
const acceptAttribute = computed(() => {
    if (!props.coverImageLimits) {
        return '';
    }

    return props.coverImageLimits.mimeTypes.join(',');
});
const maxMegabytes = computed(() => {
    if (!props.coverImageLimits) {
        return '';
    }

    return String(coverImageMegabytes(props.coverImageLimits));
});

const isModePressed = (option: CoverMode): boolean => mode.value === option;

const modeButtonClass = (option: CoverMode): string => {
    if (isModePressed(option)) {
        return 'bg-primary-selected font-medium text-primary-text';
    }

    return 'text-muted-foreground hover:text-foreground';
};

const clearErrors = (): void => {
    errorKey.value = null;
    serverError.value = null;
};

/**
 * Picking a frame drops the uploaded image, so the network gets one or the
 * other; an uploaded image drops the frame once it is in.
 */
const selectMode = (option: CoverMode): void => {
    mode.value = option;
    clearErrors();

    if (option === 'frame') {
        edit.value.coverImage = null;
    }
};

const chooseFile = (): void => {
    clearErrors();
    fileInput.value?.click();
};

const removeCoverImage = (): void => {
    clearErrors();
    edit.value.coverImage = null;
};

const uploadCoverImage = async (event: Event): Promise<void> => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file || !props.coverImageLimits) {
        return;
    }

    errorKey.value = coverImageFileError(file, props.coverImageLimits);

    if (filled(errorKey.value)) {
        return;
    }

    uploading.value = true;

    try {
        edit.value.coverImage = await uploadChunked({
            file,
            url: mediaStoreChunked.url(),
        });
        edit.value.coverOffsetMs = null;
    } catch (error) {
        if (
            error instanceof ChunkedUploadError &&
            filled(error.serverMessage)
        ) {
            serverError.value = error.serverMessage;

            return;
        }

        errorKey.value = 'rxdesk.cover.errors.upload_failed';
    } finally {
        uploading.value = false;
    }
};
</script>

<template>
    <div class="space-y-3">
        <div class="space-y-1">
            <h3 class="text-sm font-medium">
                {{ $t('posts.composer.media_editor.thumbnail_heading') }}
            </h3>
            <p class="text-xs text-muted-foreground">
                {{ $t('posts.composer.media_editor.thumbnail_hint') }}
            </p>
        </div>
        <div
            v-if="offersBoth"
            role="group"
            :aria-label="$t('rxdesk.cover.mode_label')"
            data-testid="media-editor-cover-modes"
            class="grid grid-cols-2 rounded-lg border border-border p-1"
        >
            <button
                v-for="option in COVER_MODES"
                :key="option"
                type="button"
                :aria-pressed="isModePressed(option)"
                :data-testid="`media-editor-cover-mode-${option}`"
                class="rounded-md px-2 py-1.5 text-sm whitespace-nowrap transition-colors"
                :class="modeButtonClass(option)"
                @click="selectMode(option)"
            >
                {{ $t(`rxdesk.cover.mode.${option}`) }}
            </button>
        </div>
        <label v-if="showsFramePicker" class="block space-y-1 text-sm">
            <span class="flex justify-between">
                <span>{{
                    $t('posts.composer.media_editor.thumbnail_tab')
                }}</span>
                <span
                    class="text-muted-foreground tabular-nums"
                    data-testid="media-editor-cover-value"
                    >{{ formatCoverOffset(edit.coverOffsetMs ?? 0) }}</span
                >
            </span>
            <input
                v-model.number="seconds"
                data-testid="media-editor-cover"
                :style="rangeFill(seconds, 0, maxSeconds)"
                type="range"
                min="0"
                :max="maxSeconds"
                step="0.1"
                :aria-valuetext="
                    $t('posts.composer.media_editor.thumbnail_value', {
                        seconds: seconds.toFixed(1),
                    })
                "
                class="editor-range w-full"
            />
        </label>
        <div
            v-if="showsCoverImage"
            class="space-y-3"
            data-testid="media-editor-cover-image"
        >
            <img
                v-if="edit.coverImage"
                :src="edit.coverImage.url"
                :alt="$t('rxdesk.cover.image_alt')"
                class="max-h-64 w-full rounded-lg bg-muted object-contain"
                data-testid="media-editor-cover-image-preview"
            />
            <p class="text-xs text-muted-foreground">
                {{ $t('rxdesk.cover.hint', { max: maxMegabytes }) }}
            </p>
            <input
                ref="fileInput"
                type="file"
                class="hidden"
                :accept="acceptAttribute"
                data-testid="media-editor-cover-image-input"
                @change="uploadCoverImage"
            />
            <div class="flex gap-2">
                <Button
                    v-if="hasCoverImage"
                    type="button"
                    variant="outline"
                    size="sm"
                    data-testid="media-editor-cover-image-remove"
                    :disabled="uploading"
                    @click="removeCoverImage"
                >
                    {{ $t('rxdesk.cover.remove') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-testid="media-editor-cover-image-upload"
                    :loading="uploading"
                    @click="chooseFile"
                >
                    <template v-if="hasCoverImage">{{
                        $t('rxdesk.cover.replace')
                    }}</template>
                    <template v-else>{{ $t('rxdesk.cover.upload') }}</template>
                </Button>
            </div>
            <p
                v-if="errorKey"
                class="text-sm text-destructive"
                data-testid="media-editor-cover-image-error"
            >
                {{ $t(errorKey, { max: maxMegabytes }) }}
            </p>
            <p
                v-else-if="serverError"
                class="text-sm text-destructive"
                data-testid="media-editor-cover-image-error"
            >
                {{ serverError }}
            </p>
        </div>
    </div>
</template>
