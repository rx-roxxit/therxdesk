<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\Account\LifecycleEmailType;
use App\Models\Account;
use App\Models\LifecycleEmail;
use App\Models\User;

class RecordLifecycleEmail
{
    /**
     * Record a lifecycle email for a recipient. Returns true only when this
     * call created the row, so the caller sends it exactly once.
     */
    public static function execute(Account $account, User $recipient, LifecycleEmailType $type): bool
    {
        $record = LifecycleEmail::query()->firstOrCreate(
            [
                'account_id' => $account->id,
                'user_id' => $recipient->id,
                'type' => $type->value,
            ],
            ['sent_at' => now()],
        );

        return $record->wasRecentlyCreated;
    }
}
