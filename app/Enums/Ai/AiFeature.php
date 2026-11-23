<?php

declare(strict_types=1);

namespace App\Enums\Ai;

/**
 * Every feature that calls the AI provider. Support chat and Ask the Desk
 * spend no credits: they are internal or included work, not something the
 * customer's Plan pays for.
 */
enum AiFeature: string
{
    case WritingAssistant = 'writing_assistant';
    case AltText = 'alt_text';
    case IdeaGeneration = 'idea_generation';
    case CaptionShortener = 'caption_shortener';
    case SupportChat = 'support_chat';
    case AskTheDesk = 'ask_the_desk';

    public function spendsCredits(): bool
    {
        return match ($this) {
            self::WritingAssistant,
            self::AltText,
            self::IdeaGeneration,
            self::CaptionShortener => true,
            self::SupportChat,
            self::AskTheDesk => false,
        };
    }
}
