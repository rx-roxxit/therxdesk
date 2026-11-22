<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BillingInterval } from '@/types/plan';

defineProps<{
    modelValue: BillingInterval;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: BillingInterval): void;
}>();

const select = (value: BillingInterval): void => {
    emit('update:modelValue', value);
};
</script>

<template>
    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-x-4">
        <div
            class="col-start-2 inline-flex gap-1 justify-self-center rounded-lg border border-border-strong bg-card p-[3px]"
        >
            <button
                v-for="option in ['monthly', 'yearly'] as const"
                :key="option"
                type="button"
                :aria-pressed="modelValue === option"
                :class="[
                    'h-[30px] cursor-pointer rounded-md px-3 text-sm font-medium transition-control focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring',
                    modelValue === option
                        ? 'bg-primary-selected text-primary-text'
                        : 'text-foreground hover:bg-accent',
                ]"
                :data-testid="`plan-interval-${option}`"
                @click="select(option)"
            >
                {{ $t(`billing.plans.${option}`) }}
            </button>
        </div>
        <Badge
            variant="success"
            class="col-start-3 justify-self-start"
            data-testid="plan-save-two-months"
        >
            {{ $t('billing.plans.save_two_months') }}
        </Badge>
    </div>
</template>
