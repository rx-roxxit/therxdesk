<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\AiFeature;
use App\Models\CatalogSetting;

/**
 * The two model slots, resolved to the model a call really uses: the stored
 * column when the System admin picked one, else the Config default. `apply()`
 * writes the effective model into the provider's text default right before a
 * prompt, so a change takes effect on the next call without a restart.
 */
final class AiSettings
{
    public function textModel(): string
    {
        return $this->effective(
            CatalogSetting::current()->text_model,
            (string) config('rxdesk.ai.default_text_model'),
        );
    }

    public function imageModel(): string
    {
        return $this->effective(
            CatalogSetting::current()->image_model,
            (string) config('rxdesk.ai.default_image_model'),
        );
    }

    /**
     * Self-hosted reads the provider from the environment and never overrides
     * it. Selling mode applies the slot the feature needs.
     */
    public function apply(AiFeature $feature): void
    {
        if (config('trypost.self_hosted')) {
            return;
        }

        $provider = (string) config('ai.default');
        $model = $feature === AiFeature::AltText ? $this->imageModel() : $this->textModel();

        config(["ai.providers.{$provider}.models.text.default" => $model]);
    }

    private function effective(?string $stored, string $default): string
    {
        return filled($stored) ? $stored : $default;
    }
}
