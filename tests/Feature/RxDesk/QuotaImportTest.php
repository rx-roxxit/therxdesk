<?php

declare(strict_types=1);

use App\Jobs\Media\ExportCanvaDesign;
use App\Jobs\Media\ImportRemoteMedia;
use App\Models\Account;
use App\Models\Media;
use App\Models\MediaSourceConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Http\HostResolver;
use App\Services\Media\RemoteMediaImporter;
use App\Services\Media\Sources\CanvaClient;
use App\Support\MediaImportStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake();
    config(['rxdesk.storage.default_quota' => '10MB']);

    $this->limit = 10 * 1024 * 1024;
    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
    $this->workspace = Workspace::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    subscribeAccount($this->account);

    config()->set([
        'services.google_media.client_id' => 'media-client-id',
        'services.google_media.client_secret' => 'media-client-secret',
        'services.google_media.redirect' => 'https://trypost.example/integrations/google/callback',
        'services.google_media.api_key' => 'media-api-key',
        'services.google_media.app_id' => '123456789012',
        'trypost.media_sources.google_drive.enabled' => true,
    ]);

    $this->mock(HostResolver::class)->shouldReceive('addresses')->andReturn(['142.250.0.10']);
    Http::fake([
        config('trypost.media_sources.google_drive.api').'/files/*' => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png')), 200, ['Content-Type' => 'image/png']),
    ]);
});

/**
 * @return array<string, mixed>
 */
function quotaDrivePick(): array
{
    return [
        'source' => 'google_drive',
        'access_token' => 'ya29.drive-file-only-token',
        'file' => ['id' => '1AbC_dE-fGh', 'name' => 'beach.png'],
    ];
}

test('an import is refused at the start when the storage is already full', function () {
    Queue::fake();
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => $this->limit]);

    $this->actingAs($this->owner)
        ->postJson(route('app.media.imports.store'), quotaDrivePick())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source' => "You're out of storage: you've used 10 MB of your 10 MB. Delete posts or media you no longer need, then try again."]);

    Queue::assertNotPushed(ImportRemoteMedia::class);
});

test('a downloaded file that passes the Quota fails its import and stores nothing', function (string $access, string $reason) {
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => $this->limit - 10]);
    $user = $access === 'owner' ? $this->owner : workspaceMember($this->workspace);

    $importId = $this->actingAs($user)
        ->postJson(route('app.media.imports.store'), quotaDrivePick())
        ->assertAccepted()
        ->json('import_id');

    $this->actingAs($user)
        ->getJson(route('app.media.imports.show', $importId))
        ->assertOk()
        ->assertJsonPath('status', MediaImportStatus::FAILED)
        ->assertJsonPath('reason', $reason);

    expect(Media::query()->count())->toBe(1)
        ->and(Storage::allFiles())->toBe([]);
})->with([
    'owner' => ['owner', 'storage_full'],
    'member' => ['member', 'owner_storage_full'],
]);

test('a Canva design that passes the Quota fails its export and stores nothing', function () {
    Sleep::fake();
    config()->set([
        'services.canva.client_id' => 'canva-client-id',
        'services.canva.client_secret' => 'canva-client-secret',
        'trypost.media_sources.canva.enabled' => true,
        'trypost.media_sources.canva.api' => 'https://api.canva.test/rest/v1',
        'trypost.media_sources.canva.download_hosts' => 'export-download.canva.test',
    ]);
    $download = 'https://export-download.canva.test/export/design.png';
    Http::fake([
        'https://api.canva.test/rest/v1/exports' => Http::response(['job' => ['id' => 'export-job-1', 'status' => 'in_progress']]),
        'https://api.canva.test/rest/v1/exports/export-job-1' => Http::response(['job' => ['id' => 'export-job-1', 'status' => 'success', 'urls' => [$download]]]),
        $download => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png'))),
    ]);
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => $this->limit - 10]);
    $connection = MediaSourceConnection::factory()->for($this->owner)->create(['access_token' => 'canva-access-token']);
    $importId = (string) Str::uuid();
    MediaImportStatus::pending($importId, $this->owner->id, $this->workspace->id);

    (new ExportCanvaDesign($importId, $connection->id, 'DAF-design-1', $this->workspace->id, $this->owner->id))
        ->handle(app(CanvaClient::class), app(RemoteMediaImporter::class));

    expect(data_get(MediaImportStatus::find($importId, $this->owner->id, $this->workspace->id), 'reason'))->toBe('storage_full')
        ->and(Media::query()->count())->toBe(1)
        ->and(Storage::allFiles())->toBe([]);
});
