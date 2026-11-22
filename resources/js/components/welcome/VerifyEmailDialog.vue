<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { send } from '@/routes/verification';

defineProps<{
    open: boolean;
    email: string;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const resending = ref(false);

const close = (): void => {
    emit('update:open', false);
};

const resend = (): void => {
    if (resending.value) {
        return;
    }

    resending.value = true;

    router.post(
        send.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                resending.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent data-testid="verify-email-dialog">
            <DialogHeader>
                <DialogTitle>
                    {{ $t('rxdesk.welcome_plan.verify_email.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t('rxdesk.welcome_plan.verify_email.description', {
                            email,
                        })
                    }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    data-testid="verify-email-close"
                    @click="close"
                >
                    {{ $t('rxdesk.welcome_plan.verify_email.close') }}
                </Button>
                <Button
                    type="button"
                    :loading="resending"
                    data-testid="verify-email-resend"
                    @click="resend"
                >
                    {{ $t('rxdesk.welcome_plan.verify_email.resend') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
