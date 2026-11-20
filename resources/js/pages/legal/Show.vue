<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { PublicPageMeta } from '@/types/public-page';

defineProps<{
    meta: PublicPageMeta;
    title: string;
    /** Rendered on the server from resources/legal/*.md (HTML stripped). */
    body_html: string;
}>();
</script>

<template>
    <PublicLayout :meta="meta">
        <article class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 sm:py-16">
            <h1
                class="font-heading text-3xl font-semibold tracking-tight text-foreground"
                data-testid="legal-title"
            >
                {{ title }}
            </h1>
            <div
                class="prose prose-base mt-6 max-w-none text-foreground dark:prose-invert prose-a:text-primary-text"
                data-testid="legal-body"
                v-html="body_html"
            />
        </article>
    </PublicLayout>
</template>
