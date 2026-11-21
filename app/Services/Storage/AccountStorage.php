<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\Storage\StorageRefusal;
use App\Exceptions\Storage\StorageRefused;
use App\Models\Account;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ByteSize;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * The one place that knows how much an account stores and may store. Storage
 * used is the size of every media row in the account's workspaces; rows
 * without a workspace (avatars) and workspace logos are left out, and so are
 * temporary files that never get a row (crops, chunks, derivatives).
 *
 * `refusal()` is the only decision: a new reason to refuse bytes is one more
 * check there and one more `StorageRefusal` case. The Disk guard comes first.
 */
class AccountStorage
{
    public function __construct(private readonly DiskSpace $disk) {}

    /**
     * Collections that belong to the workspace's look, not its content.
     */
    private const array UNCOUNTED_COLLECTIONS = ['logo'];

    private static int $unguardedDepth = 0;

    public function usage(Account $account): StorageUsage
    {
        return new StorageUsage($this->used($account), $this->limit($account));
    }

    public function canStore(Account $account, int $bytes): bool
    {
        return blank($this->refusal($account, $bytes));
    }

    /**
     * Why the account may not store `$bytes` more, or null when it may. Work
     * run through `unguarded()` is never refused.
     */
    public function refusal(Account $account, int $bytes): ?StorageRefusal
    {
        if (self::$unguardedDepth > 0) {
            return null;
        }

        $diskRefusal = $this->diskRefusal();

        if (filled($diskRefusal)) {
            return $diskRefusal;
        }

        if (! $this->usage($account)->fits($bytes)) {
            return StorageRefusal::Quota;
        }

        return null;
    }

    /**
     * How many more bytes the account may store before a refusal: none under
     * the Disk guard, no limit while `unguarded()`.
     */
    public function room(Account $account): int
    {
        if (self::$unguardedDepth > 0) {
            return PHP_INT_MAX;
        }

        if (filled($this->diskRefusal())) {
            return 0;
        }

        return $this->usage($account)->remaining();
    }

    /**
     * The Disk guard alone, which refuses every new file, counted or not.
     */
    public function diskRefusal(): ?StorageRefusal
    {
        if (self::$unguardedDepth > 0) {
            return null;
        }

        if ($this->diskStatus()->isGuarding()) {
            return StorageRefusal::DiskGuard;
        }

        return null;
    }

    /**
     * Throws a validation error under `$key` when the workspace's account may
     * not store `$bytes` more, worded for `$actor`.
     *
     * @throws StorageRefused
     */
    public function ensureCanStore(Workspace $workspace, int $bytes, ?User $actor, string $key = 'media'): void
    {
        $account = $workspace->account;

        $this->ensureNotRefused($this->refusal($account, $bytes), $account, $actor, $key);
    }

    /**
     * Throws a validation error when a new media row may not be stored: the
     * Disk guard refuses every row, the Quota only a row that counts (an
     * avatar or a logo never does).
     *
     * @throws StorageRefused
     */
    public function ensureCanAdd(Media $media, ?User $actor): void
    {
        $account = $this->accountOf($media);

        if (blank($account)) {
            return;
        }

        if (! $this->counts($media)) {
            $this->ensureNotRefused($this->diskRefusal(), $account, $actor);

            return;
        }

        $this->ensureNotRefused($this->refusal($account, (int) $media->size), $account, $actor);
    }

    /**
     * Whether a media row counts toward its account's Storage used.
     */
    public function counts(Media $media): bool
    {
        return filled($media->workspace_id) && ! in_array($media->collection, self::UNCOUNTED_COLLECTIONS, true);
    }

    /**
     * The message that refuses `$bytes` more for the workspace's account, or
     * null when they fit.
     */
    public function refusalMessage(Workspace $workspace, int $bytes, ?User $actor): ?string
    {
        $account = $workspace->account;
        $refusal = $this->refusal($account, $bytes);

        if (blank($refusal)) {
            return null;
        }

        return $this->messageFor($refusal, $account, $actor);
    }

    /**
     * Runs work that must never be refused, such as publishing a post that is
     * already scheduled (its next occurrence copies the media).
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    public static function unguarded(Closure $work): mixed
    {
        self::$unguardedDepth++;

        try {
            return $work();
        } finally {
            self::$unguardedDepth--;
        }
    }

    /**
     * @throws StorageRefused
     */
    private function ensureNotRefused(?StorageRefusal $refusal, Account $account, ?User $actor, string $key = 'media'): void
    {
        if (blank($refusal)) {
            return;
        }

        throw StorageRefused::because($refusal, $account, $key, $this->messageFor($refusal, $account, $actor));
    }

    /**
     * The account a media row is stored for: its workspace's, or for an
     * avatar (no workspace) its user's.
     */
    private function accountOf(Media $media): ?Account
    {
        if (filled($media->workspace_id)) {
            return Workspace::query()->with('account')->find($media->workspace_id)?->account;
        }

        if ($media->mediable_type !== Relation::getMorphAlias(User::class)) {
            return null;
        }

        return User::query()->with('account')->find($media->mediable_id)?->account;
    }

    private function messageFor(StorageRefusal $refusal, Account $account, ?User $actor): string
    {
        return $refusal->message($this->usage($account), self::speaksToOwner($account, $actor));
    }

    /**
     * Whether a refusal talks to the account's Owner ("your storage"). Anyone
     * else (a Member, or nobody signed in) hears it is the workspace owner's.
     */
    public static function speaksToOwner(?Account $account, ?User $actor): bool
    {
        if (blank($account) || blank($actor)) {
            return false;
        }

        return $account->owner_id === $actor->id;
    }

    public function used(Account $account): int
    {
        return (int) Media::query()
            ->whereIn('workspace_id', Workspace::query()->where('account_id', $account->id)->select('id'))
            ->whereNotIn('collection', self::UNCOUNTED_COLLECTIONS)
            ->sum('size');
    }

    public function limit(Account $account): int
    {
        if (filled($account->quota_bytes)) {
            return (int) $account->quota_bytes;
        }

        return self::defaultQuota();
    }

    /**
     * Sets the account's own Quota in bytes; null goes back to the default.
     */
    public function setQuota(Account $account, ?int $bytes): void
    {
        $account->forceFill(['quota_bytes' => $bytes])->save();
    }

    /**
     * The Owners' accounts: those with at least one workspace. The empty
     * account a Member signs up with stores nothing and holds no Quota.
     *
     * @return Collection<int, Account>
     */
    public function ownerAccounts(): Collection
    {
        return Account::query()->whereHas('workspaces')->with('owner')->get();
    }

    /**
     * The Owner's account of a user, or null when they own no workspaces.
     */
    public function ownerAccountOf(User $user): ?Account
    {
        return Account::query()->where('owner_id', $user->id)->whereHas('workspaces')->first();
    }

    /**
     * Every Owner's Quota added up.
     */
    public function totalQuota(): int
    {
        return (int) $this->ownerAccounts()->sum(fn (Account $account): int => $this->limit($account));
    }

    /**
     * Whether media is written to a local disk. On object storage the VPS disk
     * says nothing about how much media fits.
     */
    public function storesMediaLocally(): bool
    {
        $disk = config('filesystems.default');

        if (blank($disk)) {
            return false;
        }

        return config("filesystems.disks.{$disk}.driver") === 'local';
    }

    /**
     * The disk the media lives on and the Disk guard's threshold.
     */
    public function diskStatus(): DiskStatus
    {
        return new DiskStatus($this->disk->total(), $this->disk->free(), self::diskGuardThreshold());
    }

    /**
     * How much the VPS disk can give to media: free space, plus what the
     * Owners already store (it sits on the same disk), minus the Disk guard's
     * threshold. Space taken by the system, images, database and logs is
     * already out of the free space. Null when media is on object storage, so
     * the VPS disk's capacity says nothing about it.
     */
    public function usableSize(): ?int
    {
        if (! $this->storesMediaLocally()) {
            return null;
        }

        $usable = $this->diskStatus()->free + $this->totalUsed() - self::diskGuardThreshold();

        return max(0, $usable);
    }

    /**
     * Every Owner's Storage used added up.
     */
    public function totalUsed(): int
    {
        return (int) $this->ownerAccounts()->sum(fn (Account $account): int => $this->used($account));
    }

    public static function diskGuardThreshold(): int
    {
        $configured = (string) config('rxdesk.storage.disk_guard_threshold');

        return ByteSize::parse($configured)
            ?? throw new InvalidArgumentException("RXDESK_DISK_GUARD_THRESHOLD \"{$configured}\" is not a size such as 10GB.");
    }

    public static function defaultQuota(): int
    {
        $configured = (string) config('rxdesk.storage.default_quota');

        return ByteSize::parse($configured)
            ?? throw new InvalidArgumentException("RXDESK_DEFAULT_QUOTA \"{$configured}\" is not a size such as 5GB.");
    }
}
