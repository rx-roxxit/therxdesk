<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSplitLayout.vue';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        status?: string;
        width?: 'sm' | 'md';
    }>(),
    {
        title: undefined,
        description: undefined,
        status: undefined,
        width: 'sm',
    },
);
</script>

<template>
    <AuthLayout
        :title="title"
        :description="description"
        :status="status"
        :width="width"
    >
        <slot />
        <template v-if="$slots.footer" #footer>
            <slot name="footer" />
        </template>
    </AuthLayout>
</template>
