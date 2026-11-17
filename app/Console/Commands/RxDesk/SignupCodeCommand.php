<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Actions\SignupCode\CreateSignupCode;
use App\Actions\SignupCode\RevokeSignupCode;
use App\Models\SignupCode;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * The System admin's way to hand out Signup codes: create one (with an
 * optional note on who it is for), list them all, and revoke one nobody has
 * used yet.
 */
class SignupCodeCommand extends Command
{
    protected $signature = 'rxdesk:code
        {action : create, list or revoke}
        {code? : The code to revoke}
        {--note= : A note for a new code, such as who it is for}';

    protected $description = 'Create, list or revoke single-use Signup codes';

    public function handle(): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'create') {
            return $this->create();
        }

        if ($action === 'list') {
            return $this->list();
        }

        if ($action === 'revoke') {
            return $this->revoke();
        }

        $this->error("Unknown action \"{$action}\". Use create, list or revoke.");

        return self::FAILURE;
    }

    private function create(): int
    {
        $note = $this->option('note');
        $signupCode = CreateSignupCode::execute(filled($note) ? (string) $note : null);

        $this->info("Signup code: {$signupCode->code}");

        return self::SUCCESS;
    }

    private function list(): int
    {
        $signupCodes = SignupCode::query()->with('usedBy')->oldest()->get();

        if ($signupCodes->isEmpty()) {
            $this->info('No signup codes yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['Code', 'Note', 'Status', 'Created'],
            $signupCodes->map(fn (SignupCode $signupCode): array => [
                $signupCode->code,
                (string) $signupCode->note,
                $this->status($signupCode),
                $this->moment($signupCode->created_at),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function revoke(): int
    {
        $code = $this->argument('code');

        if (blank($code)) {
            $this->error('Pass the code to revoke: rxdesk:code revoke <code>');

            return self::FAILURE;
        }

        $signupCode = SignupCode::query()->withCode((string) $code)->first();

        if ($signupCode === null) {
            $normalizedCode = SignupCode::normalize((string) $code);
            $this->error("No signup code {$normalizedCode}.");

            return self::FAILURE;
        }

        if (! RevokeSignupCode::execute($signupCode)) {
            $refusal = $signupCode->fresh()->isRevoked() ? 'revoked' : 'used';
            $this->error("{$signupCode->code} was already {$refusal}.");

            return self::FAILURE;
        }

        $this->info("Revoked {$signupCode->code}.");

        return self::SUCCESS;
    }

    private function status(SignupCode $signupCode): string
    {
        if ($signupCode->isRevoked()) {
            return "Revoked on {$this->moment($signupCode->revoked_at)}";
        }

        if (! $signupCode->isUsed()) {
            return 'Unused';
        }

        if ($signupCode->usedBy === null) {
            return "Used by a deleted user on {$this->moment($signupCode->used_at)}";
        }

        return "Used by {$signupCode->usedBy->name} <{$signupCode->usedBy->email}> on {$this->moment($signupCode->used_at)}";
    }

    private function moment(CarbonInterface $moment): string
    {
        return "{$moment->toImmutable()->utc()->format('Y-m-d H:i')} UTC";
    }
}
