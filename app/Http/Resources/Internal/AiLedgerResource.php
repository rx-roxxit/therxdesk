<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\AiCreditUsage;
use App\Services\Ai\AiModelRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One ledger row plus what its tokens cost the business at the registry price,
 * in micro-USD. Null when the model is not in the registry.
 *
 * @mixin AiCreditUsage
 */
class AiLedgerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->account?->owner;

        return [
            'id' => $this->id,
            'account' => $this->account ? [
                'id' => $this->account->id,
                'name' => $this->account->name,
                'owner' => $owner ? [
                    'name' => $owner->name,
                    'email' => $owner->email,
                ] : null,
            ] : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'workspace' => $this->workspace ? [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
            ] : null,
            'feature' => $this->feature?->value,
            'model' => $this->model,
            'prompt_tokens' => $this->prompt_tokens,
            'completion_tokens' => $this->completion_tokens,
            'total_tokens' => $this->total_tokens,
            'credits' => $this->credits,
            'list_cost_micros' => $this->listCostMicros(),
            'created_at' => $this->created_at,
        ];
    }

    private function listCostMicros(): ?int
    {
        return app(AiModelRegistry::class)->listCostMicros(
            $this->model,
            $this->prompt_tokens,
            $this->completion_tokens,
        );
    }
}
