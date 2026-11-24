<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Account;
use App\Services\Storage\AccountStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Account's Quota after a write: its own override (or null), the Quota in
 * force, and what the Account already stores.
 *
 * @mixin Account
 */
class AccountQuotaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $usage = app(AccountStorage::class)->usage($this->resource);

        return [
            'quota_bytes' => $this->quota_bytes === null ? null : (int) $this->quota_bytes,
            'effective_quota_bytes' => $usage->limit,
            'used_bytes' => $usage->used,
        ];
    }
}
