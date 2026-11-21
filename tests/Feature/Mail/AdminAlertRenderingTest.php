<?php

declare(strict_types=1);

use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use App\Enums\User\Locale;
use App\Mail\AdminAlert as AdminAlertMail;

test('the alert email renders in every locale', function (string $locale) {
    app()->setLocale($locale);

    $html = (new AdminAlertMail(
        AlertLevel::Today,
        AdminAlertKey::CapacityRam,
        ['percent' => 88, 'threshold' => 75],
    ))->render();

    expect($html)->not->toContain('<x-')
        ->and($html)->not->toContain('@{{')
        ->and($html)->not->toContain(':percent')
        ->and($html)->not->toContain(':threshold')
        ->and($html)->not->toContain(':time')
        ->and($html)->toContain(e(__('rxdesk.alerts.conditions.capacity_ram')));
})->with(Locale::values());

test('a resolved alert shows only the cleared copy', function () {
    app()->setLocale('en');

    $html = (new AdminAlertMail(
        AlertLevel::Resolved,
        AdminAlertKey::DiskGuard,
        ['free' => '2GB', 'threshold' => '10GB'],
        [],
        true,
    ))->render();

    expect($html)->toContain(__('rxdesk.alerts.resolved_body'))
        ->and($html)->not->toContain('2GB');
});

test('the subject carries the level and the title', function (AlertLevel $level, string $prefix) {
    app()->setLocale('en');

    $key = $level === AlertLevel::Digest ? null : AdminAlertKey::DiskGuard;
    $subject = (new AdminAlertMail($level, $key, [], [], $level === AlertLevel::Resolved))->envelope()->subject;

    expect($subject)->toStartWith("{$prefix} The RX Desk: ");
})->with([
    'urgent' => [AlertLevel::Urgent, '[URGENT]'],
    'resolved' => [AlertLevel::Resolved, '[RESOLVED]'],
    'today' => [AlertLevel::Today, '[TODAY]'],
    'digest' => [AlertLevel::Digest, '[DIGEST]'],
]);

test('the digest shows only the sections that have items', function () {
    app()->setLocale('en');

    $empty = (new AdminAlertMail(AlertLevel::Digest, null, [], [
        'cleared' => [],
        'issues' => [],
        'failed_jobs' => 0,
    ]))->render();

    expect($empty)->not->toContain(__('rxdesk.alerts.digest.cleared'))
        ->and($empty)->not->toContain(__('rxdesk.alerts.digest.issues'))
        ->and($empty)->not->toContain(__('rxdesk.alerts.digest.failed_jobs'));

    $full = (new AdminAlertMail(AlertLevel::Digest, null, [], [
        'cleared' => [AdminAlertKey::CapacityRam->value],
        'issues' => [['title' => 'Boom', 'url' => null]],
        'failed_jobs' => 3,
    ]))->render();

    expect($full)->toContain(__('rxdesk.alerts.digest.cleared'))
        ->and($full)->toContain(__('rxdesk.alerts.conditions.capacity_ram'))
        ->and($full)->toContain('Boom')
        ->and($full)->toContain(__('rxdesk.alerts.digest.failed_jobs'));
});
