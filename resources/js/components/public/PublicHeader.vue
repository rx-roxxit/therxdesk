<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLogo from '@/components/AppLogo.vue';
import LanguageSelect from '@/components/LanguageSelect.vue';
import { Button } from '@/components/ui/button';
import { useGuestLocale } from '@/composables/useGuestLocale';
import { login } from '@/routes';
import { home } from '@/routes/app';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();

const isGuest = computed(() => !page.props.auth.user);
const { locale, languages } = useGuestLocale();
</script>

<template>
    <header class="border-b border-border bg-background">
        <div
            class="mx-auto flex min-h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
        >
            <Link
                :href="home()"
                class="inline-flex items-center text-lg"
                data-testid="public-logo"
            >
                <AppLogo />
            </Link>

            <div v-if="isGuest" class="flex items-center gap-2 sm:gap-3">
                <!-- On a phone the footer's picker is the one in reach. -->
                <div class="hidden sm:block">
                    <LanguageSelect
                        v-model="locale"
                        :languages="languages"
                        :label="$t('settings.preferences.language.heading')"
                        testid="public-language"
                        trigger-class="bg-background"
                    />
                </div>
                <Button as-child variant="outline">
                    <Link :href="login()" data-testid="public-header-sign-in">
                        {{ $t('rxdesk.public.nav.sign_in') }}
                    </Link>
                </Button>
            </div>
        </div>
    </header>
</template>
