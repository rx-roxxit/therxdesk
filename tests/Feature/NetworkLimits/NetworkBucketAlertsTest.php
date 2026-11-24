<?php

declare(strict_types=1);

use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use App\Mail\AdminAlert as AdminAlertMail;
use App\Models\AdminAlert;
use App\Models\NetworkCallBucket;
use App\Services\NetworkLimits\NetworkBucketMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    config([
        'trypost.self_hosted' => false,
        'rxdesk.network_limits.enabled' => true,
        'rxdesk.network_limits.buckets.tiktok' => 100,
        'rxdesk.alerts.recipient' => 'admin@example.com',
    ]);

    $this->travelTo(CarbonImmutable::now('UTC')->startOfDay()->addHours(12));
});

test('the TODAY threshold raises once and stays quiet inside the quiet period', function () {
    NetworkCallBucket::factory()->create(['network' => 'tiktok', 'calls' => 70]);

    app(NetworkBucketMonitor::class)->evaluate('tiktok');
    app(NetworkBucketMonitor::class)->evaluate('tiktok');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Today
        && $mail->key === AdminAlertKey::NetworkBucketToday
        && $mail->context['network'] === 'TikTok'
        && $mail->context['percent'] === 70.0);
});

test('the URGENT threshold raises once', function () {
    NetworkCallBucket::factory()->create(['network' => 'tiktok', 'calls' => 90]);

    app(NetworkBucketMonitor::class)->evaluate('tiktok');
    app(NetworkBucketMonitor::class)->evaluate('tiktok');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Urgent
        && $mail->key === AdminAlertKey::NetworkBucketUrgent);
});

test('an exhausted bucket raises once and records when it ran out', function () {
    $bucket = NetworkCallBucket::factory()->create(['network' => 'tiktok', 'calls' => 100]);

    app(NetworkBucketMonitor::class)->evaluate('tiktok');
    app(NetworkBucketMonitor::class)->evaluate('tiktok');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Urgent
        && $mail->key === AdminAlertKey::NetworkBucketExhausted
        && $mail->context['percent'] === 100.0);

    expect($bucket->refresh()->exhausted_at)->not->toBeNull();
});

test('dropping below the TODAY threshold clears every bucket alert for the network', function () {
    $bucket = NetworkCallBucket::factory()->create(['network' => 'tiktok', 'calls' => 70]);
    app(NetworkBucketMonitor::class)->evaluate('tiktok');

    $bucket->update(['calls' => 50]);
    app(NetworkBucketMonitor::class)->evaluate('tiktok');

    expect(AdminAlert::query()->where('key', 'network_bucket_today:tiktok')->sole()->is_open)->toBeFalse();
});

test('a new bucket day clears the alerts opened on the old one', function () {
    $alert = AdminAlert::factory()->open()->create([
        'key' => 'network_bucket_today:tiktok',
        'opened_at' => now(),
    ]);

    $this->travel(1)->day();

    app(NetworkBucketMonitor::class)->evaluateAll();

    expect($alert->refresh()->is_open)->toBeFalse()
        ->and($alert->alertKey())->toBe(AdminAlertKey::NetworkBucketToday);
});
