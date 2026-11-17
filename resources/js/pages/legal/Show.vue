<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes/app';

defineProps<{
    title: string;
    /** Rendered on the server from resources/legal/*.md (HTML stripped). */
    body_html: string;
}>();
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <Head :title="title" />
        <main class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 sm:py-16">
            <Link :href="home()" class="inline-flex text-xl">
                <AppLogo />
            </Link>
            <h1
                class="mt-10 text-3xl font-bold tracking-tight"
                data-testid="legal-title"
            >
                {{ title }}
            </h1>
            <article
                class="prose prose-base mt-6 max-w-none text-foreground dark:prose-invert prose-a:text-primary-text"
                data-testid="legal-body"
                v-html="body_html"
            />
        </main>
    </div>
</template>
