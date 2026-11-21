<?php

declare(strict_types=1);

use App\Models\BackupRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PauloCastellano\DatabaseBackup\DatabaseBackup;
use PauloCastellano\DatabaseBackup\Exceptions\DatabaseBackupException;

beforeEach(function () {
    config(['rxdesk.heartbeat.backup_url' => 'https://heartbeat.test/backup']);
});

test('a successful backup records the run and pings the backup heartbeat', function () {
    Http::fake();

    $backup = Mockery::mock(DatabaseBackup::class);
    $backup->shouldReceive('backup')
        ->once()
        ->andReturn(['path' => 'backups/x.sql.gz', 'bytes' => 10]);
    $this->app->instance(DatabaseBackup::class, $backup);

    $this->artisan('rxdesk:backup')
        ->expectsOutput('backups/x.sql.gz')
        ->assertSuccessful();

    expect(BackupRun::query()->count())->toBe(1);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://heartbeat.test/backup');
});

test('a failed backup leaves no run and pings nothing', function () {
    Http::fake();

    $backup = Mockery::mock(DatabaseBackup::class);
    $backup->shouldReceive('backup')
        ->once()
        ->andThrow(new DatabaseBackupException('boom'));
    $this->app->instance(DatabaseBackup::class, $backup);

    $thrown = null;

    try {
        $this->artisan('rxdesk:backup');
    } catch (DatabaseBackupException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull()
        ->and(BackupRun::query()->count())->toBe(0);

    Http::assertNothingSent();
});

test('a blank backup heartbeat URL still records the run and pings nothing', function () {
    Http::fake();
    config(['rxdesk.heartbeat.backup_url' => null]);

    $backup = Mockery::mock(DatabaseBackup::class);
    $backup->shouldReceive('backup')
        ->once()
        ->andReturn(['path' => 'backups/y.sql.gz', 'bytes' => 10]);
    $this->app->instance(DatabaseBackup::class, $backup);

    $this->artisan('rxdesk:backup')->assertSuccessful();

    expect(BackupRun::query()->count())->toBe(1);

    Http::assertNothingSent();
});

test('the backup is scheduled hourly in UTC and runs on one server', function () {
    config([
        'rxdesk.backup.enabled' => true,
        'laravel-database-backup.disk.bucket' => 'bucket',
    ]);

    $events = collect(app(Schedule::class)->events());

    $backup = $events->first(fn ($event): bool => str_contains((string) $event->command, 'rxdesk:backup'));

    expect($backup)->not->toBeNull()
        ->and($backup->expression)->toBe('0 * * * *')
        ->and($backup->timezone)->toBe('UTC')
        ->and($backup->withoutOverlapping)->toBeTrue()
        ->and($backup->onOneServer)->toBeTrue();
});
