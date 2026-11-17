<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Storage\AccountStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses a file while the Disk guard is active. For uploads that replace a
 * file (an avatar, a workspace logo), so the refusal comes before the old
 * one is cleared; the media row's own check stays the backstop.
 */
class DiskHasRoom implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank(app(AccountStorage::class)->diskRefusal())) {
            return;
        }

        $fail(__('rxdesk.storage.errors.disk_guard'));
    }
}
