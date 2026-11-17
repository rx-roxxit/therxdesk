<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\AccountStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A declared file size (bytes) the workspace's account may still store, so an
 * upload over the Quota is refused before its first byte lands. With `$bytes`
 * the value is not a size: the rule asks for that many bytes instead (an
 * import whose size is unknown until it downloads asks for one).
 */
class FitsStorage implements ValidationRule
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly ?User $actor,
        private readonly ?int $bytes = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $bytes = $this->bytes ?? $value;

        if (! is_numeric($bytes)) {
            return;
        }

        $message = app(AccountStorage::class)->refusalMessage($this->workspace, (int) $bytes, $this->actor);

        if ($message !== null) {
            $fail($message);
        }
    }
}
