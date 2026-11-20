<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import { filled } from '@/lib/helpers';
import type { PublicPageMeta } from '@/types/public-page';

/**
 * The public page's meta in the document head. The root view renders the
 * same tags with the same keys (`resources/views/partials/public-meta.blade.php`)
 * for crawlers; on a visit inside the site this replaces them.
 */
defineProps<{
    meta: PublicPageMeta;
}>();
</script>

<template>
    <Head :title="meta.title">
        <meta
            head-key="description"
            name="description"
            :content="meta.description"
        />
        <link head-key="canonical" rel="canonical" :href="meta.canonical" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta
            head-key="og:site_name"
            property="og:site_name"
            :content="meta.site_name"
        />
        <meta
            head-key="og:title"
            property="og:title"
            :content="meta.document_title"
        />
        <meta
            head-key="og:description"
            property="og:description"
            :content="meta.description"
        />
        <meta head-key="og:url" property="og:url" :content="meta.canonical" />
        <template v-if="filled(meta.image)">
            <meta
                head-key="og:image"
                property="og:image"
                :content="meta.image.url"
            />
            <meta
                head-key="og:image:width"
                property="og:image:width"
                :content="meta.image.width"
            />
            <meta
                head-key="og:image:height"
                property="og:image:height"
                :content="meta.image.height"
            />
        </template>
        <meta
            head-key="twitter:card"
            name="twitter:card"
            content="summary_large_image"
        />
    </Head>
</template>
