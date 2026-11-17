import { mediaRuleFor, type MediaRules } from '@/lib/contentTypeMediaRules';
import { blank } from '@/lib/helpers';
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

/** The uploaded Cover image of a video, when it has one. */
export const coverImageOf = (item: MediaItem): MediaItem | undefined =>
    item.meta?.cover_image;

/** The URL a video preview shows before it plays: its Cover image's. */
export const coverImageUrl = (item: MediaItem): string | undefined =>
    coverImageOf(item)?.url;

const withoutCoverImage = (item: MediaItem): MediaItem => {
    if (!coverImageOf(item)) {
        return item;
    }

    const meta = { ...item.meta };
    delete meta.cover_image;

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
        maxBytes: Math.min(
            ...supporting.map(
                (rule) => rule.coverImageMaxBytes ?? Number.POSITIVE_INFINITY,
            ),
        ),
        mimeTypes: supporting[0].coverImageMimeTypes,
    };
};

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
