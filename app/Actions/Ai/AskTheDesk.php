<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Ai\Agents\AskTheDeskAssistant;
use App\Enums\Ai\AiFeature;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use RuntimeException;
use Throwable;

/**
 * The System admin's Ask the Desk call. It belongs to no customer Account, so
 * the ledger row is zero-credit and accountless; it still records the tokens
 * and the model for the business numbers.
 */
final class AskTheDesk
{
    /**
     * @param  array<string, mixed>  $aggregates
     */
    public static function execute(array $aggregates, string $question): string
    {
        app(AiSettings::class)->apply(AiFeature::AskTheDesk);

        try {
            $response = (new AskTheDeskAssistant($aggregates))->prompt($question);
        } catch (Throwable $exception) {
            app(AiCredits::class)->record(null, null, null, AiFeature::AskTheDesk, 0, 0);

            throw $exception;
        }

        app(AiCredits::class)->record(
            null,
            null,
            null,
            AiFeature::AskTheDesk,
            $response->usage->promptTokens,
            $response->usage->completionTokens,
            $response->meta->model,
        );

        $answer = trim((string) $response);

        if ($answer === '') {
            throw new RuntimeException('The Ask the Desk answer was empty.');
        }

        return $answer;
    }
}
