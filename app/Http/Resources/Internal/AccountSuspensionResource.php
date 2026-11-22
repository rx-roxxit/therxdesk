<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountSuspensionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'suspended' => $this->isSuspended(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'reason' => $this->suspension_reason,
            'has_app_access' => $this->hasAppAccess(),
        ];
    }
}
