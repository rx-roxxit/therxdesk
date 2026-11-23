<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountDeletionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
