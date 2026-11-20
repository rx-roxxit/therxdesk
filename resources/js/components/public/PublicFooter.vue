<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLogo from '@/components/AppLogo.vue';
import LanguageSelect from '@/components/LanguageSelect.vue';
import { useGuestLocale } from '@/composables/useGuestLocale';
import { filled } from '@/lib/helpers';
import { home } from '@/routes/app';
import { privacy, terms } from '@/routes/legal';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();
const { locale, languages } = useGuestLocale();

/**
 * The upstream project the AGPL credit names. It stays out of the lang files
 * so `rxdesk:rebrand-lang` never renames the credit.
 */
const upstreamName = 'TryPost';

const isGuest = computed(() => !page.props.auth.user);
const site = computed(() => page.props.site);
const supportHref = computed(() => `mailto:${site.value.supportEmail}`);
const statusUrl = computed<string | null>(() => {
    const url = site.value.statusUrl;

    if (!filled(url)) {
        return null;
    }

    return url;
});
</script>

<template>
    <footer class="border-t border-border bg-background">
        <div
            class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 text-sm text-muted-foreground sm:px-6 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex flex-col gap-4">
                <Link
                    :href="home()"
                    class="inline-flex items-center text-base"
                    data-testid="public-footer-logo"
                >
                    <AppLogo />
                </Link>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <Link
                        :href="terms()"
                        class="transition-control hover:text-foreground"
                        data-testid="public-footer-terms"
                    >
                        {{ $t('rxdesk.public.footer.terms') }}
                    </Link>
                    <Link
                        :href="privacy()"
                        class="transition-control hover:text-foreground"
                        data-testid="public-footer-privacy"
                    >
                        {{ $t('rxdesk.public.footer.privacy') }}
                    </Link>
                    <a
                        :href="supportHref"
                        class="transition-control hover:text-foreground"
                        data-testid="public-footer-support"
                    >
                        {{ site.supportEmail }}
                    </a>
                    <a
                        :href="site.sourceUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="transition-control hover:text-foreground"
                        data-testid="public-footer-source"
                    >
                        {{ $t('rxdesk.public.footer.source_code') }}
                    </a>
                    <a
                        :href="site.upstreamUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="transition-control hover:text-foreground"
                        data-testid="public-footer-built-on"
                    >
                        {{
                            $t('rxdesk.public.footer.built_on', {
                                upstream: upstreamName,
                            })
                        }}
                    </a>
                    <a
                        v-if="statusUrl"
                        :href="statusUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="transition-control hover:text-foreground"
                        data-testid="public-footer-status"
                    >
                        {{ $t('rxdesk.public.footer.status') }}
                    </a>
                </div>
            </div>

            <!-- A guest's pick only switches their copy client-side; a signed-in
                 reader's language comes from their account. -->
            <LanguageSelect
                v-if="isGuest"
                v-model="locale"
                :languages="languages"
                :label="$t('settings.preferences.language.heading')"
                testid="public-footer-language"
                trigger-class="bg-background"
            />
        </div>
    </footer>
</template>
