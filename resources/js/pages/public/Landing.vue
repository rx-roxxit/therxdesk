<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import LandingFeature from '@/components/public/LandingFeature.vue';
import LandingHookStrip from '@/components/public/LandingHookStrip.vue';
import LandingNetworks from '@/components/public/LandingNetworks.vue';
import LandingScreenshot from '@/components/public/LandingScreenshot.vue';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { filled } from '@/lib/helpers';
import { login } from '@/routes';
import type { LandingNetwork } from '@/types/landing';

defineProps<{
    networks: LandingNetwork[];
}>();
</script>

<template>
    <PublicLayout>
        <section
            class="border-b border-border bg-muted"
            aria-labelledby="public-hero-title"
        >
            <div
                class="mx-auto grid w-full max-w-6xl grid-cols-1 items-center gap-10 px-4 pt-12 pb-10 sm:px-6 sm:pt-16 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:gap-14"
            >
                <div>
                    <h1
                        id="public-hero-title"
                        class="max-w-[14ch] font-heading text-4xl leading-[1.05] font-semibold tracking-tight text-balance text-foreground sm:text-5xl lg:text-6xl"
                        data-testid="public-hero-title"
                    >
                        {{ $t('rxdesk.public.hero.title') }}
                    </h1>
                    <p
                        class="mt-5 max-w-[46ch] text-lg text-muted-foreground"
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
                    <p class="mt-3 text-sm text-subtle-foreground">
                        {{ $t('rxdesk.public.hero.signup_soon') }}
                    </p>
                </div>

                <LandingScreenshot shot="calendar_list" eager />
            </div>

            <div class="mx-auto w-full max-w-6xl px-4 pb-12 sm:px-6 sm:pb-14">
                <LandingHookStrip />
            </div>
        </section>

        <LandingNetworks v-if="filled(networks)" :networks="networks" />

        <LandingFeature
            section="composer"
            :points="['per_network', 'checked']"
            shot="composer"
            canvas
        />

        <LandingFeature
            section="calendar"
            :points="['one_per_time', 'drag']"
            shot="calendar_month"
            flip
        />

        <LandingFeature
            section="workspaces"
            :points="['unlimited', 'approvals']"
            shot="approvals"
            canvas
        />

        <LandingFeature
            section="assistant"
            :points="['rewrite', 'api']"
            shot="assistant"
            flip
        />

        <section
            class="bg-muted py-14 sm:py-16"
            aria-labelledby="public-closing-title"
            data-testid="public-closing"
        >
            <div class="mx-auto w-full max-w-6xl px-4 sm:px-6">
                <h2
                    id="public-closing-title"
                    class="max-w-[22ch] font-heading text-3xl leading-tight font-semibold tracking-tight text-balance text-foreground sm:text-4xl"
                >
                    {{ $t('rxdesk.public.closing.title') }}
                </h2>
                <div class="mt-8">
                    <Button as-child size="lg">
                        <Link
                            :href="login()"
                            data-testid="public-closing-sign-in"
                        >
                            {{ $t('rxdesk.public.nav.sign_in') }}
                        </Link>
                    </Button>
                </div>
                <p class="mt-3 text-sm text-subtle-foreground">
                    {{ $t('rxdesk.public.hero.signup_soon') }}
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
