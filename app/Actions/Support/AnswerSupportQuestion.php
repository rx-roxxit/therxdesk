<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Ai\Agents\SupportAssistant;
use App\Enums\Ai\AiFeature;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use App\Support\SupportChat\HelpCorpus;
use App\Support\SupportChat\SupportBrief;
use App\Support\SupportChat\SupportChatLinks;
use Laravel\Ai\Messages\Message;
use RuntimeException;

/**
 * The ledger row still records what the call cost the service.
 */
final class AnswerSupportQuestion
{
    public static function execute(SupportConversation $conversation, SupportMessage $question): string
    {
        $user = $conversation->user;
        $workspace = $user->currentWorkspace;

        app(AiSettings::class)->apply(AiFeature::SupportChat);

        $response = (new SupportAssistant(
            corpus: app(HelpCorpus::class)->prompt(),
            brief: app(SupportBrief::class)->build($user, $workspace, $conversation->last_page),
            links: app(SupportChatLinks::class)->allowed(),
            locale: $user->locale,
            history: self::history($conversation, $question),
        ))->prompt((string) $question->content);

        $answer = trim((string) $response);

        if ($answer === '') {
            throw new RuntimeException('The Support chat answer was empty.');
        }

        app(AiCredits::class)->charge(
            $user->account,
            $user,
            $workspace,
            AiFeature::SupportChat,
            $response->usage,
            $response->meta->model,
        );

        return $answer;
    }

    /**
     * @return list<Message>
     */
    private static function history(SupportConversation $conversation, SupportMessage $question): array
    {
        return $conversation->messages()
            ->whereKeyNot($question->id)
            ->get()
            ->map(fn (SupportMessage $message): Message => new Message($message->role->value, $message->content))
            ->values()
            ->all();
    }
}
