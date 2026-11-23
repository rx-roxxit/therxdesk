<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountReviewAccessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'review_access' => $this->hasReviewAccess(),
        ];
    }
}
