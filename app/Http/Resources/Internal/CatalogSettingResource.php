<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'trial_days' => $this->trial_days,
            'tokens_per_credit' => $this->tokens_per_credit,
            'trial_ai_credits' => $this->trial_ai_credits,
            'updated_at' => $this->updated_at,
        ];
    }
}
