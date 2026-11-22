<script setup lang="ts">
import { computed } from 'vue';

import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { useGuestLocale } from '@/composables/useGuestLocale';

// Only the root div is ours: attrs from the page (data-testid, tabindex) go to
// the native select, where Playwright and the keyboard find them.
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    countries: string[];
    error?: string;
}>();

const { locale } = useGuestLocale();

type CountryOption = { code: string; name: string };

/**
 * Country names come from the browser, not the lang files: the same code list
 * the server validates is named in the guest's language.
 */
const options = computed<CountryOption[]>(() => {
    const display = new Intl.DisplayNames([locale.value], { type: 'region' });

    return [...props.countries]
        .map(
            (code): CountryOption => ({
                code,
                name: display.of(code) ?? code,
            }),
        )
        .sort((left, right): number =>
            left.name.localeCompare(right.name, locale.value),
        );
});
</script>

<template>
    <div class="grid gap-2">
        <Label for="country">{{ $t('rxdesk.country.label') }}</Label>
        <div class="w-full [&>[data-slot=native-select-wrapper]]:w-full">
            <NativeSelect
                v-bind="$attrs"
                id="country"
                name="country"
                class="w-full"
                :aria-invalid="Boolean(error)"
            >
                <NativeSelectOption value="" disabled>
                    {{ $t('rxdesk.country.select') }}
                </NativeSelectOption>
                <NativeSelectOption
                    v-for="option in options"
                    :key="option.code"
                    :value="option.code"
                >
                    {{ option.name }}
                </NativeSelectOption>
            </NativeSelect>
        </div>
        <InputError :message="error" />
    </div>
</template>
