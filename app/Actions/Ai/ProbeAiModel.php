<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Ai\Agents\AiModelProbe;
use App\Exceptions\Ai\ModelProbeFailed;
use Throwable;

/**
 * One short real call to a model, before the System admin may pick it. It
 * spends no credits, writes no ledger row and raises no admin alert: it is a
 * connectivity check, not customer work.
 */
final class ProbeAiModel
{
    public function execute(string $model): void
    {
        try {
            (new AiModelProbe)->prompt(
                'Reply with OK.',
                provider: (string) config('ai.default'),
                model: $model,
                timeout: 30,
            );
        } catch (Throwable $exception) {
            throw ModelProbeFailed::because($exception);
        }
    }
}
