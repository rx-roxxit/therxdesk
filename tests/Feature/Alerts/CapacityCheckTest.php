<?php

declare(strict_types=1);

use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use App\Mail\AdminAlert as AdminAlertMail;
use App\Models\AdminAlert;
use App\Services\Alerts\CapacityCheck;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeDiskSpace;
use Tests\Support\FakeHostMetrics;
use Tests\Support\FakeQueueMetrics;

beforeEach(function () {
    Mail::fake();
    config(['trypost.self_hosted' => false]);
    FakeHostMetrics::bind();
    FakeQueueMetrics::bind();
});

test('high memory raises a TODAY alert', function () {
    FakeHostMetrics::bind(memoryPercent: 90.0);

    app(CapacityCheck::class)->run();

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Today
        && $mail->key === AdminAlertKey::CapacityRam);
});

test('high load raises a TODAY alert', function () {
    FakeHostMetrics::bind(load: 9.0);

    app(CapacityCheck::class)->run();

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Today
        && $mail->key === AdminAlertKey::CapacityLoad);
});

test('a long queue wait raises a TODAY alert', function () {
    FakeQueueMetrics::bind(['redis:default' => 300.0]);

    app(CapacityCheck::class)->run();

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Today
        && $mail->key === AdminAlertKey::CapacityWait);
});

test('under 30 GB free raises a TODAY alert', function () {
    FakeDiskSpace::bind(free: 20 * 1024 ** 3);

    app(CapacityCheck::class)->run();

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Today
        && $mail->key === AdminAlertKey::CapacityDiskFree);
});

test('free disk near the guard is URGENT, then RESOLVED when it clears', function () {
    FakeDiskSpace::bind(free: 12 * 1024 ** 3);

    app(CapacityCheck::class)->run();

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Urgent
        && $mail->key === AdminAlertKey::DiskGuard);
    Mail::assertNotQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->key === AdminAlertKey::CapacityDiskFree);

    FakeDiskSpace::bind(free: 100 * 1024 ** 3);

    app(CapacityCheck::class)->run();

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Resolved
        && $mail->key === AdminAlertKey::DiskGuard && $mail->isResolved);
});

test('an URGENT disk alert clears the 30 GB TODAY alert', function () {
    AdminAlert::factory()->open()->create(['key' => AdminAlertKey::CapacityDiskFree->value]);
    FakeDiskSpace::bind(free: 12 * 1024 ** 3);

    app(CapacityCheck::class)->run();

    expect(AdminAlert::query()->where('key', AdminAlertKey::CapacityDiskFree->value)->first()->is_open)->toBeFalse();
    Mail::assertNotQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->key === AdminAlertKey::CapacityDiskFree);
});

test('healthy metrics send nothing', function () {
    FakeHostMetrics::bind(memoryPercent: 10.0, load: 0.5);
    FakeQueueMetrics::bind(['redis:default' => 5.0]);
    FakeDiskSpace::bind(free: 200 * 1024 ** 3);

    app(CapacityCheck::class)->run();

    Mail::assertNothingQueued();
});
