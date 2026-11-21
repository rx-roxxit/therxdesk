<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { IconCircleCheck } from '@tabler/icons-vue';
import { computed } from 'vue';

import AppLogo from '@/components/AppLogo.vue';
import LanguageSelect from '@/components/LanguageSelect.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useGuestLocale } from '@/composables/useGuestLocale';
import type { Auth } from '@/types';

const widthClass = {
    sm: 'max-w-[360px]',
    md: 'max-w-xl',
} as const;

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        status?: string;
        width?: keyof typeof widthClass;
    }>(),
    {
        title: undefined,
        description: undefined,
        status: undefined,
        width: 'sm',
    },
);

const page = usePage();

const isGuest = computed(() => !(page.props.auth as Auth).user);
const { locale, languages } = useGuestLocale();
</script>

<template>
    <div class="grid min-h-svh grid-cols-1 bg-muted min-[901px]:grid-cols-2">
        <div
            class="relative flex min-h-svh min-w-0 flex-col items-center px-4 pt-16 pb-8 sm:px-8 sm:py-16"
        >
            <div class="absolute end-4 top-4 sm:end-6 sm:top-6">
                <LanguageSelect
                    v-if="isGuest"
                    v-model="locale"
                    :languages="languages"
                    :label="$t('settings.preferences.language.heading')"
                    testid="language-picker"
                    trigger-class="bg-card"
                />
            </div>

            <div
                :class="[
                    'my-auto flex w-full flex-col gap-6',
                    widthClass[width],
                ]"
                data-testid="auth-content"
            >
                <div class="flex flex-col items-center gap-4 text-center">
                    <AppLogo
                        variant="mark"
                        class="motion-auth-logo text-[38px]"
                        data-testid="auth-logo"
                    />
                    <div
                        v-if="title || description"
                        class="flex flex-col gap-1"
                    >
                        <h1
                            v-if="title"
                            class="font-heading text-xl font-medium tracking-tight text-balance text-foreground"
                            data-testid="auth-title"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm text-balance text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>

                <Alert
                    v-if="status"
                    class="[&>svg]:text-success-text"
                    data-testid="auth-status"
                >
                    <IconCircleCheck />
                    <AlertDescription>{{ status }}</AlertDescription>
                </Alert>

                <slot />
            </div>

            <div
                v-if="$slots.footer"
                class="w-full max-w-sm pt-8 sm:absolute sm:inset-x-0 sm:bottom-6 sm:mx-auto sm:pt-0"
                data-testid="auth-footer"
            >
                <slot name="footer" />
            </div>
        </div>

        <div
            class="min-w-0 px-4 pb-8 min-[901px]:sticky min-[901px]:top-0 min-[901px]:h-svh min-[901px]:self-start min-[901px]:p-8 min-[901px]:ps-0 sm:px-8"
            data-testid="auth-preview"
        >
            <div
                class="motion-auth-fade mx-auto h-auto max-w-sm overflow-hidden rounded-[20px] bg-primary-subtle p-4 min-[901px]:h-full min-[901px]:max-w-none"
            >
                <picture class="block h-full w-full">
                    <source
                        media="(max-width: 900px)"
                        srcset="/images/rxdesk/auth-preview-phone.webp"
                    />
                    <img
                        src="/images/rxdesk/auth-preview.webp"
                        width="1052"
                        height="888"
                        :alt="$t('auth.preview_alt')"
                        class="h-auto w-full rounded-xl object-contain min-[901px]:h-full"
                        data-testid="auth-preview-image"
                    />
                </picture>
            </div>
        </div>
    </div>
</template>
