<script setup lang="ts">
import { IconLoader } from '@tabler/icons-vue';
import type { PrimitiveProps } from 'reka-ui';
import { Primitive } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

import type { ButtonVariants } from '@/components/ui/button';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: HTMLAttributes['class'];
    disabled?: boolean;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
});

const dataVariant = computed(() => {
    if (props.variant) {
        return props.variant;
    }

    return 'default';
});

const isDisabled = computed((): boolean | undefined => {
    if (props.disabled) {
        return true;
    }

    if (props.loading) {
        return true;
    }

    return undefined;
});
</script>

<template>
    <Primitive
        data-slot="button"
        :data-variant="dataVariant"
        :as="as"
        :as-child="asChild"
        :class="
            cn(buttonVariants({ variant, size }), props.class, {
                'cursor-not-allowed opacity-25': loading,
            })
        "
        :disabled="isDisabled"
    >
        <IconLoader v-if="loading" class="mx-4 size-5 animate-spin" />
        <slot v-else />
    </Primitive>
</template>
