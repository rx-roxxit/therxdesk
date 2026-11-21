<?php

declare(strict_types=1);

use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use App\Mail\AdminAlert as AdminAlertMail;
use App\Services\Alerts\AdminAlertNotifier;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config(['trypost.self_hosted' => false]);
});

test('a TODAY alert is sent once, quiet for six hours, then sent again', function () {
    $notifier = app(AdminAlertNotifier::class);

    $notifier->raise(AdminAlertKey::CapacityLoad, ['load' => 9.0, 'threshold' => 5.0]);
    Mail::assertQueuedCount(1);

    $this->travel(5)->hours();
    $notifier->raise(AdminAlertKey::CapacityLoad, ['load' => 9.0, 'threshold' => 5.0]);
    Mail::assertQueuedCount(1);

    $this->travel(1)->hours();
    $notifier->raise(AdminAlertKey::CapacityLoad, ['load' => 9.0, 'threshold' => 5.0]);
    Mail::assertQueuedCount(2);

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Today
        && $mail->key === AdminAlertKey::CapacityLoad);
});

test('an URGENT alert is sent once, and RESOLVED once when it clears', function () {
    $notifier = app(AdminAlertNotifier::class);

    $notifier->raise(AdminAlertKey::DiskGuard, ['free' => '2GB', 'threshold' => '10GB']);
    $notifier->raise(AdminAlertKey::DiskGuard, ['free' => '2GB', 'threshold' => '10GB']);
    Mail::assertQueuedCount(1);

    $notifier->clear(AdminAlertKey::DiskGuard);
    Mail::assertQueuedCount(2);

    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Resolved
        && $mail->isResolved);

    $notifier->clear(AdminAlertKey::DiskGuard);
    Mail::assertQueuedCount(2);
});
