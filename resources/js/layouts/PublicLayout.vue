<script setup lang="ts">
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
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
