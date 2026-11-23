<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An idea suggested by the model and not stored yet.
 */
class GeneratedIdeaResource extends JsonResource
{
    /**
     * @return array{title: string, body: string, ai_credits: array<string, mixed>|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => (string) data_get($this->resource, 'title'),
            'body' => (string) data_get($this->resource, 'body'),
            'ai_credits' => data_get($this->resource, 'ai_credits'),
        ];
    }
}
