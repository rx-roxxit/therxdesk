<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\CatalogSetting;
use App\Services\Ai\AiCostTable;
use App\Services\Ai\AiSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CatalogSetting
 */
class AiSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = app(AiSettings::class);
        $provider = (string) config('ai.default');

        return [
            'tokens_per_credit' => $this->tokens_per_credit,
            'trial_ai_credits' => $this->trial_ai_credits,
            'text_model' => $settings->textModel(),
            'image_model' => $settings->imageModel(),
            'key_configured' => filled(config("ai.providers.{$provider}.key")),
            'provider' => $provider,
            'cost' => app(AiCostTable::class)->build(),
            'updated_at' => $this->updated_at,
        ];
    }
}
