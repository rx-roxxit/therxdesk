<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkLimitAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'account_id' => (string) $this->account_id,
            'name' => (string) $this->name,
            'owner_email' => $this->owner_email,
            'calls' => (int) $this->calls,
            'percent' => (float) $this->percent,
        ];
    }
}
