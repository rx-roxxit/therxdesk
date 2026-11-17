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
 * upload over the Quota is refused before its first byte lands. An import
 * asks through `forImport()`, which checks the size its source declared
 * instead of the attribute's value.
 */
class FitsStorage implements ValidationRule
{
    /**
     * What an import of unknown size asks for: any file takes at least one
     * byte, so it starts only while the storage is not full.
     */
    private const int UNKNOWN_SIZE_BYTES = 1;

    public function __construct(
        private readonly Workspace $workspace,
        private readonly ?User $actor,
        private readonly ?int $bytes = null,
    ) {}

    /**
     * The rule of an import: the size its source declared must fit, and one
     * whose size is unknown until it downloads needs storage that is not full.
     */
    public static function forImport(Workspace $workspace, ?User $actor, mixed $declaredSize): self
    {
        if (! is_numeric($declaredSize)) {
            return new self($workspace, $actor, self::UNKNOWN_SIZE_BYTES);
        }

        return new self($workspace, $actor, (int) $declaredSize);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $bytes = $this->bytes ?? $value;

        if (! is_numeric($bytes)) {
            return;
        }

        $message = app(AccountStorage::class)->refusalMessage($this->workspace, (int) $bytes, $this->actor);

        if (filled($message)) {
            $fail($message);
        }
    }
}
