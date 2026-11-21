<?php

declare(strict_types=1);

use App\Models\BackupRun;
use App\Models\NightwatchIssue;
use App\Models\RestoreDrill;
use App\Services\Storage\AccountStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\FakeDiskSpace;
use Tests\Support\FakeQueueMetrics;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
    $this->siteHeaders = ['Authorization' => 'Bearer test-site-token'];
});

test('the health endpoint returns queue, backup, disk and Nightwatch figures', function () {
    FakeQueueMetrics::bind(['redis:default' => 42.5]);
    FakeDiskSpace::bind(free: 100 * 1024 ** 3, total: 200 * 1024 ** 3);

    NightwatchIssue::factory()->create([
        'issue_id' => 'issue-open',
        'title' => 'Open issue',
        'opened_at' => now(),
    ]);
    NightwatchIssue::factory()->resolved()->create([
        'issue_id' => 'issue-closed',
        'title' => 'Closed issue',
        'opened_at' => now()->subHour(),
    ]);

    DB::table('failed_jobs')->insert([
        [
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => now(),
        ],
        [
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => now()->subDays(2),
        ],
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.health'));

    $response->assertOk()
        ->assertJsonPath('backup', null)
        ->assertJsonPath('restore_drill.last_performed_at', null)
        ->assertJsonPath('restore_drill.due_at', null)
        ->assertJsonPath('restore_drill.due', true)
        ->assertJsonPath('queue.available', true)
        ->assertJsonPath('queue.waits.redis:default', 42.5)
        ->assertJsonPath('queue.max_wait_seconds', 42.5)
        ->assertJsonPath('queue.failed.total', 2)
        ->assertJsonPath('queue.failed.last_24h', 1)
        ->assertJsonPath('disk.total_bytes', 200 * 1024 ** 3)
        ->assertJsonPath('disk.free_bytes', 100 * 1024 ** 3)
        ->assertJsonPath('disk.threshold_bytes', AccountStorage::diskGuardThreshold())
        ->assertJsonPath('disk.guarding', false)
        ->assertJsonPath('nightwatch.open', 1)
        ->assertJsonPath('nightwatch.recent.0.id', 'issue-open')
        ->assertJsonCount(2, 'nightwatch.recent');
});

test('the health endpoint reports the last backup and a current restore drill', function () {
    $completedAt = now()->startOfSecond();
    $performedAt = now()->subMonth();

    BackupRun::factory()->create(['completed_at' => $completedAt]);
    RestoreDrill::factory()->create(['performed_at' => $performedAt]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.health'))
        ->assertOk()
        ->assertJsonPath('backup.last_run_at', $completedAt->toIso8601String())
        ->assertJsonPath('restore_drill.last_performed_at', $performedAt->toIso8601String())
        ->assertJsonPath('restore_drill.due_at', $performedAt->addMonthsNoOverflow(3)->toIso8601String())
        ->assertJsonPath('restore_drill.due', false);
});

test('a restore drill older than the configured months is due', function () {
    $performedAt = now()->subMonthsNoOverflow(4);

    RestoreDrill::factory()->create(['performed_at' => $performedAt]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.health'))
        ->assertOk()
        ->assertJsonPath('restore_drill.last_performed_at', $performedAt->toIso8601String())
        ->assertJsonPath('restore_drill.due', true);
});

test('the site token cannot read health', function () {
    $this->withHeaders($this->siteHeaders)
        ->getJson(route('internal.health'))
        ->assertNotFound();
});

test('the health endpoint answers 404 without a token', function () {
    $this->getJson(route('internal.health'))->assertNotFound();
});

test('the health endpoint needs the acting System admin email', function () {
    $this->withHeaders(['Authorization' => 'Bearer test-admin-token'])
        ->getJson(route('internal.health'))
        ->assertUnprocessable();
});
