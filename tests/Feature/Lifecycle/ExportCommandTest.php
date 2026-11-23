<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
});

function exportableAccount(): array
{
    Storage::fake();

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $owner->account_id]);
    $channel = SocialAccount::factory()->create(['workspace_id' => $workspace->id]);
    $post = Post::factory()->forAccount($channel)->create([
        'content' => 'Hello export',
        'user_id' => $owner->id,
    ]);
    $media = Media::factory()->ownedByPost($post)->stored()->create([
        'original_filename' => 'photo.jpg',
    ]);

    return [$owner, $post, $media];
}

test('the export zip holds the posts as JSON and their media', function () {
    [$owner, $post, $media] = exportableAccount();
    $output = sys_get_temp_dir().'/rxdesk-export-'.Str::uuid();

    $this->artisan('rxdesk:export', [
        '--account' => $owner->account_id,
        '--output' => $output,
    ])->assertSuccessful();

    $zipPath = glob($output.'/*.zip')[0];
    $zip = new ZipArchive;

    expect($zip->open($zipPath))->toBeTrue();

    $payload = json_decode((string) $zip->getFromName('posts.json'), true);
    $file = $payload['posts'][0]['media'][0]['file'];

    expect($payload['account']['id'])->toBe($owner->account_id)
        ->and($payload['account']['owner_email'])->toBe($owner->email)
        ->and($payload['posts'])->toHaveCount(1)
        ->and($payload['posts'][0]['id'])->toBe($post->id)
        ->and($payload['posts'][0]['content'])->toBe('Hello export')
        ->and($payload['posts'][0]['media'][0]['filename'])->toBe('photo.jpg')
        ->and($file)->toBeString()
        ->and($zip->getFromName((string) $file))->toBe('media bytes');

    $zip->close();
    File::deleteDirectory($output);
});

test('a dry run writes nothing and reports the counts', function () {
    [$owner] = exportableAccount();
    $output = sys_get_temp_dir().'/rxdesk-export-'.Str::uuid();

    $this->artisan('rxdesk:export', [
        '--account' => $owner->account_id,
        '--output' => $output,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(is_dir($output))->toBeFalse();
});

test('the export needs an account id', function () {
    $this->artisan('rxdesk:export')->assertFailed();
});

test('nothing is exported while the app is self-hosted', function () {
    config(['trypost.self_hosted' => true]);

    [$owner] = exportableAccount();

    $this->artisan('rxdesk:export', ['--account' => $owner->account_id])->assertFailed();
});
