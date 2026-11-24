<?php

declare(strict_types=1);

use App\Console\Commands\Analytics\DispatchAccountDailyAnalytics;
use App\Console\Commands\Analytics\DispatchPublicationDiscovery;
use App\Console\Commands\Analytics\DispatchPublicationMetrics;
use App\Console\Commands\CheckSocialConnections;
use App\Console\Commands\CheckUpcomingPostConnections;
use App\Console\Commands\ProcessScheduledPosts;
use App\Console\Commands\PruneExpiredPostHistoryCommand;
use App\Console\Commands\PruneTemporaryUploadsCommand;
use App\Console\Commands\PruneWebhookLogs;
use App\Console\Commands\ReconcileGoogleBusinessPosts;
use App\Console\Commands\RecoverStuckPosts;
use App\Console\Commands\RefreshExpiringTokens;
use App\Console\Commands\Repurpose\PollRepurposes;
use App\Console\Commands\ResolveTikTokVideoIds;
use App\Console\Commands\RssFeed\PollRssFeeds;
use App\Console\Commands\RxDesk\BackupCommand;
use App\Console\Commands\RxDesk\BusinessSnapshotCommand;
use App\Console\Commands\RxDesk\CapacityCommand;
use App\Console\Commands\RxDesk\DigestCommand;
use App\Console\Commands\RxDesk\LifecycleCommand;
use App\Console\Commands\RxDesk\PruneAskTheDeskCommand;
use App\Console\Commands\RxDesk\PruneSupportChatCommand;
use App\Console\Commands\SyncPostHogLifecycleStages;
use App\Enums\SocialAccount\Platform;
use App\Jobs\Analytics\FinalizeAccountDailySnapshots;
use App\Jobs\PingHeartbeat;
use App\Services\PostHogService;
use App\Support\Analytics\DiscoverySchedule;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessScheduledPosts::class)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command(CheckSocialConnections::class)->daily()->withoutOverlapping()->onOneServer();
Schedule::command(CheckUpcomingPostConnections::class)->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(RefreshExpiringTokens::class)->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(RecoverStuckPosts::class)->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(ReconcileGoogleBusinessPosts::class)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(ResolveTikTokVideoIds::class)->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(PruneWebhookLogs::class)->daily()->withoutOverlapping()->onOneServer();
Schedule::command(PruneExpiredPostHistoryCommand::class)->daily()->withoutOverlapping()->onOneServer();
Schedule::command(PruneTemporaryUploadsCommand::class)->hourly()->withoutOverlapping()->onOneServer();
Schedule::command(PollRepurposes::class)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(PollRssFeeds::class)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(SyncPostHogLifecycleStages::class)->hourly()->when(PostHogService::isEnabled(...))->withoutOverlapping()->onOneServer();
Schedule::command(DispatchAccountDailyAnalytics::class)
    ->dailyAt('02:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command(DispatchPublicationDiscovery::class, ['--except-platform' => Platform::X->value])
    ->cron(DiscoverySchedule::cron((int) config('trypost.analytics.discovery_interval_hours')))
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command(DispatchPublicationDiscovery::class, ['--platform' => Platform::X->value])
    ->cron(DiscoverySchedule::cron((int) config('trypost.analytics.x_discovery_interval_hours')))
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command(DispatchPublicationMetrics::class)
    ->dailyAt('03:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::job(new FinalizeAccountDailySnapshots)
    ->name('analytics:finalize-account-daily')
    ->dailyAt('23:30')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::job(new FinalizeAccountDailySnapshots(daysAgo: 1))
    ->name('analytics:finalize-account-daily-recovery')
    ->dailyAt('00:30')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(LifecycleCommand::class)
    ->dailyAt('01:00')
    ->timezone('UTC')
    ->when(fn (): bool => ! config('trypost.self_hosted'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(PruneSupportChatCommand::class)
    ->dailyAt('04:00')
    ->timezone('UTC')
    ->when(fn (): bool => ! config('trypost.self_hosted'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(PruneAskTheDeskCommand::class)
    ->dailyAt('04:10')
    ->timezone('UTC')
    ->when(fn (): bool => ! config('trypost.self_hosted'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(BusinessSnapshotCommand::class)
    ->dailyAt('00:05')
    ->timezone('UTC')
    ->when(fn (): bool => ! config('trypost.self_hosted'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(CapacityCommand::class)
    ->everyFiveMinutes()
    ->when(fn (): bool => ! config('trypost.self_hosted'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(DigestCommand::class)
    ->dailyAt((string) config('rxdesk.alerts.digest_at'))
    ->timezone((string) config('rxdesk.alerts.timezone'))
    ->when(fn (): bool => ! config('trypost.self_hosted'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(BackupCommand::class)
    ->hourly()
    ->timezone('UTC')
    ->when(fn (): bool => (bool) config('rxdesk.backup.enabled') && filled(config('laravel-database-backup.disk.bucket')))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new PingHeartbeat)
    ->name('rxdesk:heartbeat')
    ->everyFiveMinutes()
    ->when(fn (): bool => ! config('trypost.self_hosted'))
    ->withoutOverlapping()
    ->onOneServer();
