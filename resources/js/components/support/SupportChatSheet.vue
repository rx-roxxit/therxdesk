<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import {
    IconArrowUp,
    IconCopy,
    IconMail,
    IconPlus,
    IconThumbDown,
    IconThumbUp,
    IconX,
} from '@tabler/icons-vue';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';

import {
    index as loadConversation,
    store as storeConversation,
} from '@/actions/App/Http/Controllers/App/SupportConversationController';
import { store as sendHandoff } from '@/actions/App/Http/Controllers/App/SupportHandoffController';
import {
    retry as retryMessage,
    feedback as sendFeedback,
    store as storeMessage,
} from '@/actions/App/Http/Controllers/App/SupportMessageController';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useSupportChat } from '@/composables/useSupportChat';
import date from '@/date';
import { filled } from '@/lib/helpers';
import type {
    SupportChatErrorBody,
    SupportChatFeedback,
    SupportChatHandoff,
    SupportChatLimits,
    SupportChatMessage,
    SupportChatPayload,
    SupportChatTurn,
} from '@/types/support';

const { isOpen, close } = useSupportChat();

const page = usePage();

const MESSAGE_MAX_CHARACTERS = 2000;
const SUGGESTIONS = ['connect_channel', 'schedule_post', 'trial'] as const;

const loadHttp = useHttp<Record<string, never>, SupportChatPayload>({});
const conversationHttp = useHttp<Record<string, never>, SupportChatPayload>({});
const messageHttp = useHttp<{ message: string; page: string }, SupportChatTurn>(
    {
        message: '',
        page: '',
    },
);
const retryHttp = useHttp<Record<string, never>, SupportChatTurn>({});
const feedbackHttp = useHttp<
    { feedback: SupportChatFeedback | null },
    { message: SupportChatMessage }
>({ feedback: null });
const handoffHttp = useHttp<
    { message: string; email: string; page: string },
    SupportChatHandoff
>({ message: '', email: '', page: '' });

const messages = ref<SupportChatMessage[]>([]);
const conversationId = ref<string | null>(null);
const limits = ref<SupportChatLimits | null>(null);
const input = ref('');
const loading = ref(false);
const sending = ref(false);
const loadFailed = ref(false);
const genericError = ref(false);
const minuteError = ref(false);
const failedMessageId = ref<string | null>(null);
const escalatedAt = ref<string | null>(null);
const handoffOpen = ref(false);
const handoffSending = ref(false);
const handoffSent = ref(false);
const handoffFailed = ref(false);
const handoffMessage = ref('');
const handoffEmail = ref('');
const transcriptCopied = ref(false);

const scroller = useTemplateRef<HTMLElement>('scroller');

const conversationLimitReached = computed(
    (): boolean =>
        limits.value !== null &&
        limits.value.conversation.used >= limits.value.conversation.max,
);

const dailyLimitReached = computed(
    (): boolean =>
        limits.value !== null &&
        limits.value.daily.used >= limits.value.daily.max,
);

const isLocked = computed(
    (): boolean => conversationLimitReached.value || dailyLimitReached.value,
);

const dailyResetLabel = computed((): string =>
    limits.value === null ? '' : date.formatTime(limits.value.daily.resets_at),
);

const conversationMax = computed(
    (): number => limits.value?.conversation.max ?? 0,
);

const dailyMax = computed((): number => limits.value?.daily.max ?? 0);

const userEmail = computed((): string => page.props.auth.user.email);

const supportEmail = computed((): string => {
    const email = page.props.supportEmail;

    if (typeof email !== 'string') {
        return '';
    }

    return email;
});

const handoffMailto = computed((): string => `mailto:${supportEmail.value}`);

const escalated = computed((): boolean => escalatedAt.value !== null);

const canSendHandoff = computed(
    (): boolean =>
        !handoffSending.value &&
        filled(handoffMessage.value.trim()) &&
        filled(handoffEmail.value.trim()),
);

const tooLong = computed(
    (): boolean => input.value.length > MESSAGE_MAX_CHARACTERS,
);

const canSend = computed(
    (): boolean =>
        !loading.value &&
        !sending.value &&
        !isLocked.value &&
        !tooLong.value &&
        input.value.trim() !== '',
);

const showsEmptyState = computed(
    (): boolean =>
        !loading.value &&
        !loadFailed.value &&
        !sending.value &&
        failedMessageId.value === null &&
        messages.value.length === 0,
);

const showsTyping = computed((): boolean => sending.value);

const isFailed = (message: SupportChatMessage): boolean =>
    message.id === failedMessageId.value;

const isAnswer = (message: SupportChatMessage): boolean =>
    message.role === 'assistant';

const answerHtml = (message: SupportChatMessage): string => message.html ?? '';

const feedbackChoice = (
    message: SupportChatMessage,
    choice: SupportChatFeedback,
): boolean => message.feedback === choice;

interface HttpErrorBody {
    response?: { data?: unknown };
}

const errorBody = (error: unknown): SupportChatErrorBody | null => {
    const data = (error as HttpErrorBody).response?.data;

    if (typeof data !== 'string') {
        return null;
    }

    try {
        return JSON.parse(data) as SupportChatErrorBody;
    } catch {
        return null;
    }
};

const scrollToBottom = async (): Promise<void> => {
    await nextTick();
    scroller.value?.scrollTo({ top: scroller.value.scrollHeight });
};

const load = async (): Promise<void> => {
    if (loading.value) {
        return;
    }

    loading.value = true;
    loadFailed.value = false;

    try {
        const payload = await loadHttp.get(loadConversation.url());

        conversationId.value = payload.conversation?.id ?? null;
        messages.value = payload.messages;
        limits.value = payload.limits;
        escalatedAt.value = payload.conversation?.escalated_at ?? null;
        failedMessageId.value = null;
        handoffOpen.value = false;
    } catch {
        loadFailed.value = true;
    } finally {
        loading.value = false;
        await scrollToBottom();
    }
};

const ensureConversation = async (): Promise<string> => {
    if (conversationId.value !== null) {
        return conversationId.value;
    }

    const payload = await conversationHttp.post(storeConversation.url());
    conversationId.value = payload.conversation?.id ?? null;
    limits.value = payload.limits;
    escalatedAt.value = payload.conversation?.escalated_at ?? null;

    if (conversationId.value === null) {
        throw new Error('No Support chat conversation');
    }

    return conversationId.value;
};

const failedUserMessage = (
    messageId: string,
    text: string,
): SupportChatMessage => ({
    id: messageId,
    role: 'user',
    created_at: new Date().toISOString(),
    feedback: null,
    html: null,
    text,
});

const handleSendError = (error: unknown, text: string): void => {
    const refusal = errorBody(error);

    if (refusal === null) {
        genericError.value = true;

        return;
    }

    if (refusal.limits !== undefined) {
        limits.value = refusal.limits;
    }

    if (refusal.code === 'minute') {
        minuteError.value = true;

        return;
    }

    if (refusal.code === 'conversation' || refusal.code === 'daily') {
        return;
    }

    if (
        refusal.code === 'unavailable' &&
        text !== '' &&
        refusal.message_id !== undefined
    ) {
        failedMessageId.value = refusal.message_id;
        messages.value = [
            ...messages.value,
            failedUserMessage(refusal.message_id, text),
        ];
        input.value = '';

        return;
    }

    genericError.value = true;
};

const sendMessage = async (text: string): Promise<void> => {
    const trimmed = text.trim();

    if (trimmed === '' || sending.value || isLocked.value) {
        return;
    }

    if (trimmed.length > MESSAGE_MAX_CHARACTERS) {
        return;
    }

    sending.value = true;
    genericError.value = false;
    minuteError.value = false;

    try {
        const conversation = await ensureConversation();

        messageHttp.message = trimmed;
        messageHttp.page = window.location.pathname;

        const turn = await messageHttp.post(storeMessage.url(conversation));

        messages.value = [...messages.value, ...turn.messages];
        limits.value = turn.limits;
        failedMessageId.value = null;
        input.value = '';
    } catch (error) {
        handleSendError(error, trimmed);
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
};

const submitMessage = (): void => {
    void sendMessage(input.value);
};

const sendSuggestion = (text: string): void => {
    void sendMessage(text);
};

const retryFailed = async (): Promise<void> => {
    if (failedMessageId.value === null || sending.value) {
        return;
    }

    const messageId = failedMessageId.value;

    sending.value = true;
    genericError.value = false;
    minuteError.value = false;

    try {
        const turn = await retryHttp.post(retryMessage.url(messageId));

        messages.value = [
            ...messages.value,
            ...turn.messages.filter((message) => message.role === 'assistant'),
        ];
        limits.value = turn.limits;
        failedMessageId.value = null;
    } catch (error) {
        handleSendError(error, '');
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
};

const pickFeedback = async (
    message: SupportChatMessage,
    choice: SupportChatFeedback,
): Promise<void> => {
    const previous = message.feedback;
    const next = previous === choice ? null : choice;

    message.feedback = next;
    feedbackHttp.feedback = next;

    try {
        await feedbackHttp.patch(sendFeedback.url(message.id));
    } catch {
        message.feedback = previous;
        genericError.value = true;
    }
};

const plainText = (message: SupportChatMessage): string => {
    if (message.role === 'user') {
        return message.text ?? '';
    }

    const element = document.createElement('div');
    element.innerHTML = message.html ?? '';

    return element.textContent ?? '';
};

const transcriptText = (): string =>
    messages.value
        .map(plainText)
        .filter((text) => filled(text))
        .join('\n\n');

const openHandoff = (): void => {
    handoffOpen.value = true;
    handoffSent.value = false;
    handoffFailed.value = false;
    transcriptCopied.value = false;
    handoffEmail.value = userEmail.value;
};

const closeHandoff = (): void => {
    handoffOpen.value = false;
    handoffSent.value = false;
    handoffFailed.value = false;
    transcriptCopied.value = false;
};

const submitHandoff = async (): Promise<void> => {
    if (!canSendHandoff.value) {
        return;
    }

    handoffSending.value = true;
    handoffFailed.value = false;
    transcriptCopied.value = false;

    try {
        const conversation = await ensureConversation();

        handoffHttp.message = handoffMessage.value.trim();
        handoffHttp.email = handoffEmail.value.trim();
        handoffHttp.page = window.location.pathname;

        const payload = await handoffHttp.post(sendHandoff.url(conversation));

        escalatedAt.value = payload.escalated_at;
        handoffSent.value = true;
        handoffMessage.value = '';
    } catch {
        handoffFailed.value = true;
    } finally {
        handoffSending.value = false;
    }
};

const copyTranscript = async (): Promise<void> => {
    try {
        await navigator.clipboard.writeText(transcriptText());
        transcriptCopied.value = true;
    } catch {
        transcriptCopied.value = false;
    }
};

const startNewConversation = async (): Promise<void> => {
    if (sending.value) {
        return;
    }

    sending.value = true;
    genericError.value = false;
    minuteError.value = false;

    try {
        const payload = await conversationHttp.post(storeConversation.url());

        conversationId.value = payload.conversation?.id ?? null;
        messages.value = payload.messages;
        limits.value = payload.limits;
        escalatedAt.value = payload.conversation?.escalated_at ?? null;
        failedMessageId.value = null;
        handoffOpen.value = false;
        handoffSent.value = false;
        handoffFailed.value = false;
        input.value = '';
    } catch {
        genericError.value = true;
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
};

const handleOpenChange = (open: boolean): void => {
    if (!open) {
        close();
    }
};

watch(
    isOpen,
    (open) => {
        if (open) {
            void load();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Sheet :open="isOpen" @update:open="handleOpenChange">
        <SheetContent
            side="right"
            class="flex w-full flex-col gap-0 p-0 sm:max-w-md"
            :show-close-button="false"
            data-testid="support-chat-sheet"
        >
            <div class="flex items-start gap-2 border-b border-border p-4">
                <div class="min-w-0 flex-1">
                    <SheetTitle
                        class="text-base"
                        data-testid="support-chat-title"
                    >
                        {{ $t('rxdesk.support_chat.title') }}
                    </SheetTitle>
                    <SheetDescription class="text-xs">
                        {{ $t('rxdesk.support_chat.disclosure') }}
                    </SheetDescription>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    :disabled="sending"
                    :aria-label="$t('rxdesk.support_chat.new_conversation')"
                    :title="$t('rxdesk.support_chat.new_conversation')"
                    data-testid="support-chat-new"
                    @click="startNewConversation"
                >
                    <IconPlus class="size-4" />
                </Button>
                <SheetClose as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('common.close')"
                        data-testid="support-chat-close"
                    >
                        <IconX class="size-4" />
                    </Button>
                </SheetClose>
            </div>

            <div
                ref="scroller"
                class="flex-1 space-y-4 overflow-y-auto p-4"
                aria-live="polite"
                data-testid="support-chat-messages"
            >
                <div v-if="loading" class="space-y-3">
                    <div class="h-10 w-2/3 animate-pulse rounded-lg bg-muted" />
                    <div
                        class="ms-auto h-10 w-1/2 animate-pulse rounded-lg bg-muted"
                    />
                </div>

                <div v-else-if="loadFailed" class="space-y-3 text-sm">
                    <p class="text-muted-foreground">
                        {{ $t('rxdesk.support_chat.load_failed') }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        data-testid="support-chat-load-retry"
                        @click="load"
                    >
                        {{ $t('rxdesk.support_chat.retry') }}
                    </Button>
                </div>

                <div
                    v-else-if="showsEmptyState"
                    class="space-y-3"
                    data-testid="support-chat-empty"
                >
                    <div class="space-y-1">
                        <p class="font-medium">
                            {{ $t('rxdesk.support_chat.empty_title') }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ $t('rxdesk.support_chat.empty_description') }}
                        </p>
                    </div>
                    <div class="flex flex-col items-start gap-2">
                        <Button
                            v-for="suggestion in SUGGESTIONS"
                            :key="suggestion"
                            variant="outline"
                            size="sm"
                            class="h-auto text-start whitespace-normal"
                            :disabled="isLocked"
                            :data-testid="`support-chat-suggestion-${suggestion}`"
                            @click="
                                sendSuggestion(
                                    $t(
                                        `rxdesk.support_chat.suggestions.${suggestion}`,
                                    ),
                                )
                            "
                        >
                            {{
                                $t(
                                    `rxdesk.support_chat.suggestions.${suggestion}`,
                                )
                            }}
                        </Button>
                    </div>
                </div>

                <template v-else>
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        class="space-y-1"
                        :data-testid="`support-chat-message-${message.role}`"
                    >
                        <div v-if="isAnswer(message)" class="space-y-1">
                            <div
                                class="support-answer rounded-lg bg-muted/60 px-3 py-2 text-sm"
                                v-html="answerHtml(message)"
                            />
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors outline-none hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                                    :class="
                                        feedbackChoice(message, 'up')
                                            ? 'text-foreground'
                                            : ''
                                    "
                                    :aria-label="
                                        $t('rxdesk.support_chat.feedback_up')
                                    "
                                    :aria-pressed="
                                        feedbackChoice(message, 'up')
                                    "
                                    :data-testid="`support-chat-feedback-up-${message.id}`"
                                    @click="pickFeedback(message, 'up')"
                                >
                                    <IconThumbUp class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors outline-none hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                                    :class="
                                        feedbackChoice(message, 'down')
                                            ? 'text-foreground'
                                            : ''
                                    "
                                    :aria-label="
                                        $t('rxdesk.support_chat.feedback_down')
                                    "
                                    :aria-pressed="
                                        feedbackChoice(message, 'down')
                                    "
                                    :data-testid="`support-chat-feedback-down-${message.id}`"
                                    @click="pickFeedback(message, 'down')"
                                >
                                    <IconThumbDown class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <div
                            v-else
                            class="ms-auto w-fit max-w-[85%] rounded-lg bg-primary px-3 py-2 text-sm text-primary-foreground"
                        >
                            {{ message.text }}
                        </div>

                        <div
                            v-if="isFailed(message)"
                            class="space-y-2 text-sm"
                            data-testid="support-chat-failed"
                        >
                            <p class="text-muted-foreground">
                                {{ $t('rxdesk.support_chat.unavailable') }}
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                :loading="sending"
                                data-testid="support-chat-failed-retry"
                                @click="retryFailed"
                            >
                                {{ $t('rxdesk.support_chat.retry') }}
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="showsTyping"
                        class="flex items-center gap-1 text-sm text-muted-foreground"
                        data-testid="support-chat-typing"
                    >
                        <span
                            class="size-1.5 animate-bounce rounded-full bg-current"
                        />
                        <span
                            class="size-1.5 animate-bounce rounded-full bg-current [animation-delay:150ms]"
                        />
                        <span
                            class="size-1.5 animate-bounce rounded-full bg-current [animation-delay:300ms]"
                        />
                        <span class="sr-only">{{
                            $t('rxdesk.support_chat.typing')
                        }}</span>
                    </div>
                </template>
            </div>

            <div class="space-y-2 border-t border-border p-3">
                <template v-if="handoffOpen">
                    <p class="text-sm font-medium">
                        {{ $t('rxdesk.support_chat.handoff.title') }}
                    </p>

                    <template v-if="handoffSent">
                        <p
                            class="text-sm text-muted-foreground"
                            data-testid="support-handoff-sent"
                        >
                            {{ $t('rxdesk.support_chat.handoff.success') }}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            data-testid="support-handoff-close"
                            @click="closeHandoff"
                        >
                            {{ $t('rxdesk.support_chat.handoff.back_to_chat') }}
                        </Button>
                    </template>

                    <template v-else>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('rxdesk.support_chat.handoff.description') }}
                        </p>
                        <p
                            v-if="handoffFailed"
                            class="text-xs text-muted-foreground"
                            data-testid="support-handoff-error"
                        >
                            {{ $t('rxdesk.support_chat.handoff.failed') }}
                        </p>

                        <form class="space-y-2" @submit.prevent="submitHandoff">
                            <label
                                for="support-handoff-message"
                                class="block text-xs font-medium"
                            >
                                {{
                                    $t(
                                        'rxdesk.support_chat.handoff.message_label',
                                    )
                                }}
                            </label>
                            <textarea
                                id="support-handoff-message"
                                v-model="handoffMessage"
                                rows="3"
                                class="max-h-32 min-h-16 w-full resize-none rounded-lg border border-border bg-transparent px-3 py-2 text-sm outline-none focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                                :placeholder="
                                    $t(
                                        'rxdesk.support_chat.handoff.message_placeholder',
                                    )
                                "
                                data-testid="support-handoff-message"
                            />
                            <label
                                for="support-handoff-email"
                                class="block text-xs font-medium"
                            >
                                {{
                                    $t(
                                        'rxdesk.support_chat.handoff.email_label',
                                    )
                                }}
                            </label>
                            <input
                                id="support-handoff-email"
                                v-model="handoffEmail"
                                type="text"
                                inputmode="email"
                                autocomplete="email"
                                class="w-full rounded-lg border border-border bg-transparent px-3 py-2 text-sm outline-none focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                                data-testid="support-handoff-email"
                            />
                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="closeHandoff"
                                >
                                    {{
                                        $t('rxdesk.support_chat.handoff.cancel')
                                    }}
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    :loading="handoffSending"
                                    :disabled="!canSendHandoff"
                                    data-testid="support-handoff-submit"
                                >
                                    {{ $t('rxdesk.support_chat.handoff.send') }}
                                </Button>
                            </div>
                        </form>

                        <div
                            v-if="handoffFailed"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                data-testid="support-handoff-copy"
                                @click="copyTranscript"
                            >
                                <IconCopy class="size-3.5" />
                                {{
                                    $t(
                                        'rxdesk.support_chat.handoff.copy_transcript',
                                    )
                                }}
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <a
                                    :href="handoffMailto"
                                    data-testid="support-handoff-mailto"
                                >
                                    <IconMail class="size-3.5" />
                                    {{ $t('rxdesk.help.support_email') }}
                                </a>
                            </Button>
                            <p
                                v-if="transcriptCopied"
                                class="text-xs text-muted-foreground"
                                data-testid="support-handoff-copied"
                            >
                                {{ $t('rxdesk.support_chat.handoff.copied') }}
                            </p>
                        </div>
                    </template>
                </template>

                <template v-else>
                    <p
                        v-if="conversationLimitReached"
                        class="text-xs text-muted-foreground"
                        data-testid="support-chat-limit-conversation"
                    >
                        {{
                            $t('rxdesk.support_chat.limits.conversation', {
                                max: conversationMax,
                            })
                        }}
                    </p>
                    <p
                        v-if="dailyLimitReached"
                        class="text-xs text-muted-foreground"
                        data-testid="support-chat-limit-daily"
                    >
                        {{
                            $t('rxdesk.support_chat.limits.daily', {
                                max: dailyMax,
                                time: dailyResetLabel,
                            })
                        }}
                    </p>
                    <p
                        v-if="minuteError"
                        class="text-xs text-muted-foreground"
                        data-testid="support-chat-limit-minute"
                    >
                        {{ $t('rxdesk.support_chat.limits.minute') }}
                    </p>
                    <p v-if="tooLong" class="text-xs text-muted-foreground">
                        {{ $t('rxdesk.support_chat.too_long') }}
                    </p>
                    <p
                        v-if="genericError"
                        class="text-xs text-muted-foreground"
                        data-testid="support-chat-error"
                    >
                        {{ $t('rxdesk.support_chat.error') }}
                    </p>
                    <p
                        v-if="escalated"
                        class="text-xs text-muted-foreground"
                        data-testid="support-chat-escalated"
                    >
                        {{ $t('rxdesk.support_chat.handoff.waiting') }}
                    </p>

                    <form
                        class="flex items-end gap-2"
                        @submit.prevent="submitMessage"
                    >
                        <textarea
                            v-model="input"
                            rows="2"
                            class="max-h-32 min-h-10 flex-1 resize-none rounded-lg border border-border bg-transparent px-3 py-2 text-sm outline-none focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring disabled:opacity-50"
                            :placeholder="
                                $t('rxdesk.support_chat.input_placeholder')
                            "
                            :disabled="isLocked || loading"
                            data-testid="support-chat-input"
                            @keydown.enter.exact.prevent="submitMessage"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            :loading="sending"
                            :disabled="!canSend"
                            :aria-label="$t('rxdesk.support_chat.send')"
                            data-testid="support-chat-send"
                        >
                            <IconArrowUp class="size-4" />
                        </Button>
                    </form>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="w-full"
                        data-testid="support-handoff-open"
                        @click="openHandoff"
                    >
                        <IconMail class="size-3.5" />
                        {{ $t('rxdesk.support_chat.handoff.contact') }}
                    </Button>
                </template>
            </div>
        </SheetContent>
    </Sheet>
</template>

<style scoped>
.support-answer :deep(p) {
    margin: 0 0 0.5rem;
}

.support-answer :deep(p:last-child) {
    margin-bottom: 0;
}

.support-answer :deep(ul),
.support-answer :deep(ol) {
    margin: 0.25rem 0 0.5rem;
    padding-inline-start: 1.25rem;
}

.support-answer :deep(a) {
    color: var(--primary-strong);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.support-answer :deep(code) {
    font-size: 0.85em;
}
</style>
