<?php

declare(strict_types=1);

namespace App\Exceptions\Storage;

use App\Enums\Storage\StorageRefusal;
use App\Models\Account;
use App\Models\User;
use App\Services\Storage\AccountStorage;
use Illuminate\Validation\ValidationException;

/**
 * A validation error raised when `AccountStorage` refuses new bytes. A queued
 * import catches it and records `importFailure()` for the composer instead.
 */
class StorageRefused extends ValidationException
{
    public StorageRefusal $refusal = StorageRefusal::Quota;

    public ?Account $account = null;

    public static function because(StorageRefusal $refusal, Account $account, string $key, string $message): self
    {
        $exception = static::withMessages([$key => $message]);
        $exception->refusal = $refusal;
        $exception->account = $account;

        return $exception;
    }

    /**
     * The import failure reason the composer shows to `$actor`.
     */
    public function importFailure(?User $actor): string
    {
        return $this->refusal->importFailure(AccountStorage::speaksToOwner($this->account, $actor));
    }
}
