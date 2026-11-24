<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Account;
use App\Services\Storage\AccountStorage;
use App\Services\Storage\StorageUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the admin app's Storage list. Every number comes from
 * AccountStorage, never from summing media here.
 *
 * @mixin Account
 */
class StorageRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $usage = app(AccountStorage::class)->usage($this->resource);

        return [
            'account' => [
                'id' => $this->id,
                'name' => $this->name,
            ],
            'owner' => $this->owner === null ? null : [
                'email' => $this->owner->email,
            ],
            'used_bytes' => $usage->used,
            'quota_bytes' => $usage->limit,
            'percent' => $this->percent($usage),
        ];
    }

    private function percent(StorageUsage $usage): float
    {
        if ($usage->limit === 0) {
            return 0.0;
        }

        return round($usage->used / $usage->limit * 100, 1);
    }
}
