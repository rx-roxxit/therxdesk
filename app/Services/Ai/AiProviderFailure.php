<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\AiFeature;
use App\Enums\Alerts\AdminAlertKey;
use App\Services\Alerts\AdminAlertNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Our side of the AI provider failed (down, rate-limited, model gone). The
 * customer spends nothing; the System admin gets an URGENT alert naming the
 * model and the error.
 */
final class AiProviderFailure
{
    public function report(Throwable $exception, AiFeature $feature): void
    {
        $model = $this->model();

        Log::error('AI provider call failed.', [
            'model' => $model,
            'feature' => $feature->value,
            'error' => $exception->getMessage(),
        ]);

        app(AdminAlertNotifier::class)->raise(AdminAlertKey::AiProviderFailure, [
            'model' => $model,
            'error' => $exception->getMessage(),
            'feature' => $feature->value,
        ]);
    }

    private function model(): string
    {
        $provider = (string) config('ai.default');

        return (string) (config("ai.providers.{$provider}.models.text.default") ?? $provider);
    }
}
