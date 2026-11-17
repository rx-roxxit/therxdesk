import { mediaRuleFor, type MediaRules } from '@/lib/contentTypeMediaRules';
import { blank, filled } from '@/lib/helpers';
import { isVideo } from '@/lib/mediaType';
import type { MediaItem } from '@/types/media';

/**
 * The client side of `App\Support\Media\CoverImage`: a Cover image goes on
 * the single video of a post whose content type takes one.
 */
export const takesCoverImage = (
    contentType: string,
    media: MediaItem[],
): boolean => {
    if (!mediaRuleFor(contentType)?.supports_cover_image) {
        return false;
    }

    if (media.length !== 1) {
        return false;
    }

    return isVideo(media[0]);
};

/** The key of a video's Cover image in its media meta (`SyncOwnedMedia::COVER_IMAGE`). */
export const COVER_IMAGE_KEY = 'cover_image';

/** The uploaded Cover image of a video, when it has one. */
export const coverImageOf = (item: MediaItem): MediaItem | undefined =>
    item.meta?.[COVER_IMAGE_KEY];

/** The meta a Cover image adds to its video: nothing when it has none. */
export const coverImageMeta = (
    cover: MediaItem | null,
): { cover_image?: MediaItem } => {
    if (!filled(cover)) {
        return {};
    }

    return { [COVER_IMAGE_KEY]: cover };
};

/** Whether an edit's Cover image is not the one the item has. */
export const coverImageChanged = (
    edited: MediaItem | null,
    item: MediaItem,
): boolean => {
    const stored = coverImageOf(item);

    if (!filled(edited) && !filled(stored)) {
        return false;
    }

    if (!filled(edited) || !filled(stored)) {
        return true;
    }

    return edited.id !== stored.id;
};

/** The URL a video preview shows before it plays: its Cover image's. */
export const coverImageUrl = (item: MediaItem): string | undefined =>
    coverImageOf(item)?.url;

const withoutCoverImage = (item: MediaItem): MediaItem => {
    if (!coverImageOf(item)) {
        return item;
    }

    const meta = { ...item.meta };
    delete meta[COVER_IMAGE_KEY];

    return { ...item, meta };
};

/**
 * The media a destination sends: a channel whose post cannot take the
 * shared Cover image gets the video without it.
 */
export const mediaForContentType = (
    contentType: string,
    media: MediaItem[],
): MediaItem[] => {
    if (takesCoverImage(contentType, media)) {
        return media;
    }

    return media.map(withoutCoverImage);
};

/** What a Cover image file may be for these content types' rules. */
export type CoverImageLimits = { maxBytes: number; mimeTypes: string[] };

/** One rule's size cap: no cap when the rule names none. */
const coverImageCapOf = (rule: MediaRules): number => {
    if (filled(rule.coverImageMaxBytes)) {
        return rule.coverImageMaxBytes;
    }

    return Number.POSITIVE_INFINITY;
};

/**
 * The limits of a Cover image across the given rules (the lowest size cap),
 * or null when none of them takes one.
 */
export const coverImageLimitsFor = (
    rules: MediaRules[],
): CoverImageLimits | null => {
    const supporting = rules.filter((rule) => rule.supportsCoverImage);

    if (blank(supporting)) {
        return null;
    }

    return {
        maxBytes: Math.min(...supporting.map(coverImageCapOf)),
        mimeTypes: supporting[0].coverImageMimeTypes,
    };
};

/** The most bytes the limits allow, as whole megabytes, the way messages name it. */
export const coverImageMegabytes = (limits: CoverImageLimits): number =>
    Math.floor(limits.maxBytes / 1024 / 1024);

/** The lang key of why a file cannot be a Cover image, or null when it can. */
export const coverImageFileError = (
    file: File,
    limits: CoverImageLimits,
): string | null => {
    if (!limits.mimeTypes.includes(file.type)) {
        return 'rxdesk.cover.errors.type';
    }

    if (file.size > limits.maxBytes) {
        return 'rxdesk.cover.errors.too_large';
    }

    return null;
};
