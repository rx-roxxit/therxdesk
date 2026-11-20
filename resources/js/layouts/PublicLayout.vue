<script setup lang="ts">
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
import PublicPageHead from '@/components/public/PublicPageHead.vue';
import type { PublicPageMeta } from '@/types/public-page';

defineProps<{
    /** The page's title, description, canonical and Open Graph image. */
    meta: PublicPageMeta;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <PublicPageHead :meta="meta" />

        <a
            href="#public-main"
            class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-foreground focus:shadow-md focus:ring-2 focus:ring-ring"
        >
            {{ $t('rxdesk.public.skip_to_content') }}
        </a>

        <PublicHeader />

        <main id="public-main" class="flex-1">
            <slot />
        </main>

        <PublicFooter />
    </div>
</template>
