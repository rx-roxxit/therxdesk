<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PublicLayout from '@/layouts/PublicLayout.vue';

defineProps<{
    title: string;
    /** Rendered on the server from resources/legal/*.md (HTML stripped). */
    body_html: string;
}>();
</script>

<template>
    <PublicLayout>
        <Head :title="title" />
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
