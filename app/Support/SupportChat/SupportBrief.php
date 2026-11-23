<?php

declare(strict_types=1);

namespace App\Support\SupportChat;

use App\Enums\Post\Status;
use App\Models\Account;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiCredits;
use App\Services\Storage\AccountStorage;
use App\Support\ByteSize;
use Illuminate\Support\Str;

/**
 * The live facts the Support chat gets each turn, read fresh. It never carries
 * an email, a name or an avatar, and the agent has no tools, so a prompt can
 * only read this brief.
 */
final class SupportBrief
{
    private const int FAILED_POSTS = 5;

    private const int MAX_ERROR_CHARS = 200;

    public function __construct(
        private readonly AiCredits $credits,
        private readonly AccountStorage $storage,
    ) {}

    public function build(User $user, Workspace $workspace, ?string $page): string
    {
        $lines = $this->accountLines($user);

        $lines[] = '## Workspace';
        $lines[] = '- Workspaces the user belongs to: '.$user->accountWorkspaces()->count();
        $lines = array_merge($lines, $this->channelLines($workspace), $this->failedPostLines($workspace));
        $lines[] = '- Pending approvals: '.$this->pendingApprovals($user, $workspace);

        if (filled($page)) {
            $lines[] = "- Page the user has open: {$page}";
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function accountLines(User $user): array
    {
        if (! $user->isAccountOwner() || $user->account === null) {
            return [];
        }

        $account = $user->account;
        $lines = ['## Account', '- Plan: '.($account->plan?->name ?? 'none'), '- Status: '.$this->accountStatus($account)];

        $trialEndsAt = $account->activeTrialEndsAt();

        if ($trialEndsAt !== null) {
            $lines[] = '- Trial ends: '.$trialEndsAt->toDateString();
        }

        $lines[] = '- AI credits: '.$this->credits->remaining($account).' of '.$this->credits->allowance($account).' left';

        $usage = $this->storage->usage($account);
        $lines[] = '- Storage: '.ByteSize::format($usage->used).' of '.ByteSize::format($usage->limit);

        return $lines;
    }

    public function accountStatus(Account $account): string
    {
        if ($account->isSuspended()) {
            return 'suspended';
        }

        if ($account->isComplimentary()) {
            return 'complimentary plan';
        }

        if ($account->isOnTrial()) {
            return 'trialing';
        }

        if ($account->isPastDue()) {
            return 'past due';
        }

        if ($account->isLapsed()) {
            return 'lapsed';
        }

        if ($account->hasActiveSubscription()) {
            return 'active';
        }

        return $account->subscriptionStatus();
    }

    /**
     * @return list<string>
     */
    private function channelLines(Workspace $workspace): array
    {
        $channels = $workspace->socialAccounts()->get();

        if ($channels->isEmpty()) {
            return ['- Channels: none connected'];
        }

        $lines = ['- Channels:'];

        foreach ($channels as $channel) {
            $error = $channel->error_message;
            $line = "  - {$channel->platform->label()} @{$channel->username}: {$channel->status->value}";

            if (filled($error)) {
                $line .= ' (last error: '.$this->shorten($error).')';
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function failedPostLines(Workspace $workspace): array
    {
        $posts = Post::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', Status::Failed)
            ->latestAttempt()
            ->limit(self::FAILED_POSTS)
            ->get();

        if ($posts->isEmpty()) {
            return ['- Recent failed posts: none'];
        }

        $lines = ['- Recent failed posts:'];

        foreach ($posts as $post) {
            $when = ($post->scheduled_at ?? $post->published_at ?? $post->created_at)?->toDateString();
            $platform = $post->platform?->label() ?? 'unknown network';
            $error = $this->shorten((string) $post->error_message);

            $lines[] = "  - {$platform}, {$when}: {$error}";
        }

        return $lines;
    }

    private function pendingApprovals(User $user, Workspace $workspace): int
    {
        $requester = $user->canPublishDirectlyIn($workspace) ? null : $user;

        return Post::query()
            ->where('workspace_id', $workspace->id)
            ->pendingApproval()
            ->visiblePendingApprovalsFor($requester)
            ->count();
    }

    private function shorten(string $text): string
    {
        return (string) Str::of($text)->squish()->limit(self::MAX_ERROR_CHARS, '…');
    }
}
