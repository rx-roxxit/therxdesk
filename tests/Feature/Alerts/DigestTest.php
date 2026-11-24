<?php

declare(strict_types=1);

use App\Console\Commands\RxDesk\DigestCommand;
use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use App\Mail\AdminAlert as AdminAlertMail;
use App\Models\AccountNetworkCall;
use App\Models\AdminAlert;
use App\Models\NightwatchIssue;
use App\Models\RestoreDrill;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    config(['trypost.self_hosted' => false]);
});

test('the digest sends nothing on an empty day', function () {
    $this->travelTo(now('Asia/Bangkok')->startOfDay()->addHours(8));

    RestoreDrill::factory()->create(['performed_at' => now()->subMonth()]);

    $this->artisan(DigestCommand::class)->assertExitCode(0);

    Mail::assertNothingQueued();
});

test('a missing restore drill makes the digest ask for one', function () {
    $this->travelTo(now('Asia/Bangkok')->startOfDay()->addHours(8));

    $this->artisan(DigestCommand::class)->assertExitCode(0);

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->items['restore_drill']['due'] === true
        && $mail->items['restore_drill']['last_performed_at'] === null);
});

test('a restore drill older than three months makes the digest ask for one', function () {
    $this->travelTo(now('Asia/Bangkok')->startOfDay()->addHours(8));

    $performedAt = now()->subMonthsNoOverflow(4);
    RestoreDrill::factory()->create(['performed_at' => $performedAt]);

    $this->artisan(DigestCommand::class)->assertExitCode(0);

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->items['restore_drill']['due'] === true
        && $mail->items['restore_drill']['last_performed_at'] === $performedAt->timezone('Asia/Bangkok')->format('Y-m-d'));
});

test('the digest sends one mail with cleared alerts, new issues and failed jobs', function () {
    $this->travelTo(now('Asia/Bangkok')->startOfDay()->addHours(8));

    AdminAlert::factory()->create([
        'key' => AdminAlertKey::CapacityLoad->value,
        'is_open' => false,
        'resolved_at' => now()->subHours(2),
    ]);
    NightwatchIssue::factory()->create([
        'title' => 'Something broke',
        'opened_at' => now()->subHours(1),
    ]);
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'boom',
        'failed_at' => now('Asia/Bangkok')->subDay()->setTime(12, 0)->utc(),
    ]);

    $this->artisan(DigestCommand::class)->assertExitCode(0);

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Digest
        && array_column($mail->items['cleared'], 'key') === [AdminAlertKey::CapacityLoad->value]
        && $mail->items['issues'][0]['title'] === 'Something broke'
        && $mail->items['failed_jobs'] === 1);
});

test('the digest names the Accounts that used a shared bucket the previous UTC day', function () {
    $this->travelTo(now('Asia/Bangkok')->startOfDay()->addHours(8));
    config(['rxdesk.network_limits.buckets.tiktok' => 100]);

    $user = User::factory()->create();
    $user->account->update(['name' => 'Acme']);

    $previousDay = now('UTC')->subDay()->startOfDay()->toDateString();

    AccountNetworkCall::factory()->create([
        'account_id' => $user->account_id,
        'network' => 'tiktok',
        'date' => $previousDay,
        'calls' => 40,
    ]);
    AccountNetworkCall::factory()->create([
        'network' => 'tiktok',
        'date' => $previousDay,
        'calls' => 5,
    ]);

    $this->artisan(DigestCommand::class)->assertExitCode(0);

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Digest
        && $mail->items['bucket_accounts']['total'] === 1
        && $mail->items['bucket_accounts']['rows'][0]['account'] === 'Acme'
        && $mail->items['bucket_accounts']['rows'][0]['network'] === 'TikTok'
        && $mail->items['bucket_accounts']['rows'][0]['percent'] === 40.0);
});

test('the digest and heartbeat are scheduled in selling mode', function () {
    $events = collect(app(Schedule::class)->events());

    $digest = $events->first(fn ($event): bool => str_contains((string) $event->command, 'rxdesk:digest'));
    expect($digest)->not->toBeNull()
        ->and($digest->expression)->toBe('0 8 * * *')
        ->and($digest->timezone)->toBe('Asia/Bangkok')
        ->and($digest->withoutOverlapping)->toBeTrue()
        ->and($digest->onOneServer)->toBeTrue();

    $capacity = $events->first(fn ($event): bool => str_contains((string) $event->command, 'rxdesk:capacity'));
    expect($capacity)->not->toBeNull()
        ->and($capacity->expression)->toBe('*/5 * * * *');

    $heartbeat = $events->first(fn ($event): bool => $event->description === 'rxdesk:heartbeat');
    expect($heartbeat)->not->toBeNull()
        ->and($heartbeat->expression)->toBe('*/5 * * * *')
        ->and($heartbeat->withoutOverlapping)->toBeTrue()
        ->and($heartbeat->onOneServer)->toBeTrue();
});
