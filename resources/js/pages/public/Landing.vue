<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { login } from '@/routes';
</script>

<template>
    <PublicLayout>
        <section class="border-b border-border bg-muted">
            <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
                <h1
                    class="max-w-3xl font-heading text-4xl font-semibold tracking-tight text-balance text-foreground sm:text-5xl"
                    data-testid="public-hero-title"
                >
                    {{ $t('rxdesk.public.hero.title') }}
                </h1>
                <p
                    class="mt-5 max-w-2xl text-lg text-muted-foreground"
                    data-testid="public-hero-lead"
                >
                    {{ $t('rxdesk.public.hero.lead') }}
                </p>
                <div class="mt-8">
                    <Button as-child size="lg">
                        <Link :href="login()" data-testid="public-sign-in">
                            {{ $t('rxdesk.public.nav.sign_in') }}
                        </Link>
                    </Button>
                </div>
                <p class="mt-4 text-sm text-muted-foreground">
                    {{ $t('rxdesk.public.hero.signup_soon') }}
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
