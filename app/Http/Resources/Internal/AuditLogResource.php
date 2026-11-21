<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->actor,
            'action' => $this->action,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'before' => $this->before,
            'after' => $this->after,
            'created_at' => $this->created_at,
        ];
    }
}
