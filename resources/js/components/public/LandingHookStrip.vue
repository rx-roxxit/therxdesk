<script setup lang="ts">
/**
 * The hero's hook: what sets the desk apart, as a two-cell ledger header.
 * Pre-launch it carries only the two unlimited cells; the selling site adds
 * its price cell here.
 */
</script>

<template>
    <dl
        class="grid grid-cols-2 overflow-hidden rounded-xl border border-border bg-card shadow-sm sm:max-w-md"
        data-testid="public-hook-strip"
    >
        <div class="border-e border-border px-4 py-3">
            <dt class="text-xs text-subtle-foreground">
                {{ $t('rxdesk.public.hook.workspaces') }}
            </dt>
            <dd
                class="font-heading text-xl leading-tight font-semibold tracking-tight text-foreground"
            >
                {{ $t('rxdesk.public.hook.unlimited') }}
            </dd>
        </div>
        <div class="px-4 py-3">
            <dt class="text-xs text-subtle-foreground">
                {{ $t('rxdesk.public.hook.channels') }}
            </dt>
            <dd
                class="font-heading text-xl leading-tight font-semibold tracking-tight text-foreground"
            >
                {{ $t('rxdesk.public.hook.unlimited') }}
            </dd>
        </div>
    </dl>
</template>
