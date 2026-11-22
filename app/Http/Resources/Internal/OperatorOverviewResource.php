<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The current Operator and the ones it replaced.
 */
class OperatorOverviewResource extends JsonResource
{
    /**
     * @param  Collection<int, Operator>  $history
     */
    public function __construct(private readonly ?Operator $current, private readonly Collection $history)
    {
        parent::__construct($current);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'current' => $this->current === null ? null : OperatorResource::make($this->current)->resolve($request),
            'history' => OperatorResource::collection($this->history)->resolve($request),
        ];
    }
}
