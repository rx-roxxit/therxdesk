/** An answer arrives with its HTML already rendered. */
export interface SupportChatMessage {
    id: string;
    role: 'user' | 'assistant';
    created_at: string;
    feedback: SupportChatFeedback | null;
    html: string | null;
    text: string | null;
}

export type SupportChatFeedback = 'up' | 'down';

export interface SupportChatLimits {
    conversation: { used: number; max: number };
    daily: { used: number; max: number; resets_at: string };
}

export interface SupportConversationSummary {
    id: string;
    last_page: string | null;
    last_message_at: string | null;
    escalated_at: string | null;
}

/** `GET /support-chat` and `POST /support-chat/conversations`. */
export interface SupportChatPayload {
    conversation: SupportConversationSummary | null;
    messages: SupportChatMessage[];
    limits: SupportChatLimits;
}

export interface SupportChatTurn {
    messages: SupportChatMessage[];
    limits: SupportChatLimits;
}

/** The 200 body after the hand-off email really went out. */
export interface SupportChatHandoff {
    escalated_at: string;
}

/** The 429 body: which limit stopped the message. */
export interface SupportChatLimitRefusal {
    code: 'conversation' | 'daily' | 'minute';
    retry_after: number | null;
    limits: SupportChatLimits;
}

/** The 502 body when the provider fails. */
export interface SupportChatFailure {
    code: 'unavailable';
    message_id: string;
    limits: SupportChatLimits;
}

/** Any body the chat endpoint can send for a refused or failed message. */
export type SupportChatErrorBody = Partial<
    SupportChatLimitRefusal & SupportChatFailure
>;
