<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Media\Source;
use App\Enums\PostPlatform\ContentType;
use App\Models\Account;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake();
    fakePublicDns();
    config(['rxdesk.storage.default_quota' => '10MB']);

    $this->mb = 1024 * 1024;
    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
    $this->workspace = Workspace::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    subscribeAccount($this->account);

    $this->x = SocialAccount::factory()->x()->create(['workspace_id' => $this->workspace->id]);
    $this->bluesky = SocialAccount::factory()->bluesky()->create(['workspace_id' => $this->workspace->id]);
});

/**
 * A stored temporary upload of `$megabytes`.
 */
function quotaUpload(Workspace $workspace, int $megabytes): Media
{
    $upload = Media::factory()->temporaryUpload($workspace)->create([
        'type' => 'image',
        'mime_type' => 'image/png',
        'original_filename' => 'photo.png',
        'size' => $megabytes * 1024 * 1024,
    ]);
    Storage::put($upload->path, (string) file_get_contents(base_path('tests/fixtures/1x1.png')));

    return $upload;
}

/**
 * One upload posted to the X and Bluesky channels: the first post takes the
 * upload, the second gets its own copy.
 */
function postToBothChannels(object $test, User $user, Media $upload): TestResponse
{
    return $test->actingAs($user)->from(route('app.posts.index'))->post(route('app.posts.store'), [
        'status' => 'draft',
        'content' => 'One upload, two channels',
        'media' => [MediaItem::fromMedia($upload)->toArray()],
        'destinations' => [
            ['social_account_id' => $test->x->id, 'content_type' => ContentType::XPost->value, 'meta' => []],
            ['social_account_id' => $test->bluesky->id, 'content_type' => ContentType::BlueskyPost->value, 'meta' => []],
        ],
    ]);
}

test('a per-channel copy that passes the Quota refuses the whole save and writes nothing', function () {
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 5 * $this->mb]);
    $upload = quotaUpload($this->workspace, 4);
    $filesBefore = Storage::allFiles();

    postToBothChannels($this, $this->owner, $upload)
        ->assertSessionHasErrors(['media' => "You're out of storage: you've used 9 MB of your 10 MB. Delete posts or media you no longer need, then try again."]);

    expect(Post::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(2)
        ->and($upload->fresh()->collection)->toBe(Media::COLLECTION_UPLOADS)
        ->and(Storage::allFiles())->toEqual($filesBefore);
});

test('per-channel copies count against the Quota and save while they fit', function () {
    $upload = quotaUpload($this->workspace, 4);

    postToBothChannels($this, $this->owner, $upload)->assertSessionHasNoErrors();

    expect(Post::query()->count())->toBe(2)
        ->and(Media::query()->sum('size'))->toEqual(8 * $this->mb);
});

test("a Member's copy over the Quota names the workspace owner", function () {
    $member = workspaceMember($this->workspace, 'admin');
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 5 * $this->mb]);

    postToBothChannels($this, $member, quotaUpload($this->workspace, 4))
        ->assertSessionHasErrors(['media' => "The workspace owner's storage is full, so this file can't be added. Ask them to free some space, then try again."]);

    expect(Post::query()->count())->toBe(0);
});

test('an Unsplash import over the Quota is refused and stores nothing', function () {
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 10 * $this->mb]);
    config()->set('services.unsplash.access_key', 'unsplash-key');
    $fake = UploadedFile::fake()->image('photo.jpg', 80, 60);
    $image = file_get_contents($fake->getPathname());
    Http::fake([
        Source::Unsplash->downloadHosts()[0].'/*' => Http::response($image, 200, ['Content-Type' => 'image/jpeg']),
        config('trypost.media_sources.unsplash.api').'/*' => Http::response(['url' => 'https://example.test']),
    ]);

    $this->actingAs($this->owner)
        ->postJson(route('app.media.store-from-url'), [
            'url' => 'https://'.Source::Unsplash->downloadHosts()[0].'/photo-1?w=1080',
            'filename' => 'unsplash.jpg',
            'download_location' => config('trypost.media_sources.unsplash.api').'/photos/test/download',
            'photo_id' => 'test',
            'author_name' => 'Ana',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media' => "You're out of storage: you've used 10 MB of your 10 MB. Delete posts or media you no longer need, then try again."]);

    expect(Media::query()->count())->toBe(1)
        ->and(Storage::allFiles())->toBe([]);
});

test('an API upload by the Owner over the Quota is refused in the Owner\'s words and stores nothing', function () {
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 10 * $this->mb]);
    $token = data_get(createApiTestToken(['workspace' => $this->workspace]), 'plain_token');

    $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
        ->post(route('api.uploads.create'), ['media' => UploadedFile::fake()->image('shot.png', 40, 40)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media' => "You're out of storage: you've used 10 MB of your 10 MB. Delete posts or media you no longer need, then try again."]);

    expect(Media::query()->count())->toBe(1)
        ->and(Storage::allFiles())->toBe([]);
});

test('a workspace logo does not count, so it is accepted over the Quota', function () {
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 10 * $this->mb]);

    $this->actingAs($this->owner)
        ->post(route('app.workspace.upload-logo'), ['photo' => UploadedFile::fake()->image('logo.jpg', 200, 200)])
        ->assertSessionHasNoErrors();

    expect($this->workspace->fresh()->has_logo)->toBeTrue();
});
