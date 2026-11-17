<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Platform\ListContentTypesTool;
use App\Mcp\Tools\Post\AttachMediaFromUploadTool;
use App\Mcp\Tools\Post\AttachMediaFromUrlTool;
use App\Mcp\Tools\Post\CreatePostsTool;
use App\Mcp\Tools\Post\CreatePostTool;
use App\Mcp\Tools\Post\GetPostTool;
use App\Mcp\Tools\Post\UpdatePostTool;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Support\Media\CoverImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Queue::fake();
    ['user' => $this->user, 'workspace' => $this->workspace, 'token' => $this->token] = parityContext();
    $this->instagram = SocialAccount::factory()->instagram()->create(['workspace_id' => $this->workspace->id]);

    Http::fake([
        'example.com/cover.png' => fn () => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png')), 200, ['Content-Type' => 'image/png']),
        'example.com/missing.png' => fn () => Http::response('', 404),
        'example.com/page.html' => fn () => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
    ]);
});

/**
 * A stored video upload, the way request-media-upload-tool leaves it.
 */
function coverParityVideo(object $test): Media
{
    return Media::factory()->temporaryUpload($test->workspace)->stored()->video()->create();
}

/**
 * A stored image upload with `$attributes` over a JPEG.
 *
 * @param  array<string, mixed>  $attributes
 */
function coverParityImage(object $test, array $attributes = []): Media
{
    return Media::factory()->temporaryUpload($test->workspace)->stored()->create($attributes);
}

/**
 * @param  array<string, mixed>  $cover
 * @return array<string, mixed>
 */
function coverParityPayload(object $test, Media $video, array $cover, string $type = ContentType::InstagramReel->value): array
{
    return [
        'content' => 'Reel',
        'social_account_id' => $test->instagram->id,
        'content_type' => $type,
        'media' => [['upload_token' => $video->upload_token, 'meta' => ['cover_image' => $cover]]],
    ];
}

/**
 * What a post stores of its Cover, with nothing that differs between two runs.
 *
 * @return array{cover: array<string, mixed>|null, owned: int, frame: mixed}
 */
function coverParityStored(Post $post): array
{
    $item = data_get($post->fresh()->media, '0', []);
    $cover = data_get($item, 'meta.cover_image');

    return [
        'cover' => $cover === null ? null : ['type' => data_get($cover, 'type'), 'mime_type' => data_get($cover, 'mime_type')],
        'owned' => $post->ownedMedia()->count(),
        'frame' => data_get($item, 'meta.cover_offset_ms'),
    ];
}

/**
 * @return Illuminate\Support\Collection<int, Post>
 */
function coverParityPosts(object $test): Illuminate\Support\Collection
{
    return Post::query()->where('workspace_id', $test->workspace->id)->oldest()->get();
}

test('a Cover from an upload token is stored the same through api and mcp', function () {
    $api = coverParityPayload($this, coverParityVideo($this), ['upload_token' => coverParityImage($this)->upload_token]);
    $mcp = coverParityPayload($this, coverParityVideo($this), ['upload_token' => coverParityImage($this)->upload_token]);

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.store'), $api)->assertCreated();
    TryPostServer::actingAs($this->user)->tool(CreatePostTool::class, $mcp)->assertOk();

    [$apiPost, $mcpPost] = coverParityPosts($this);

    expect(coverParityStored($apiPost))->toEqual(coverParityStored($mcpPost))
        ->toEqual(['cover' => ['type' => 'image', 'mime_type' => 'image/jpeg'], 'owned' => 2, 'frame' => null]);
});

test('a Cover from a url is hosted the same through api and mcp', function () {
    $api = coverParityPayload($this, coverParityVideo($this), ['url' => 'https://example.com/cover.png']);
    $mcp = coverParityPayload($this, coverParityVideo($this), ['url' => 'https://example.com/cover.png']);

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.store'), $api)->assertCreated();
    TryPostServer::actingAs($this->user)->tool(CreatePostTool::class, $mcp)->assertOk();

    [$apiPost, $mcpPost] = coverParityPosts($this);

    expect(coverParityStored($apiPost))->toEqual(coverParityStored($mcpPost))
        ->toEqual(['cover' => ['type' => 'image', 'mime_type' => 'image/png'], 'owned' => 2, 'frame' => null]);
});

test('a Cover in a batch destination is stored the same through api and mcp', function () {
    $payload = fn (): array => [
        'status' => 'draft',
        'content' => 'Reel',
        'destinations' => [['social_account_id' => $this->instagram->id, 'content_type' => ContentType::InstagramReel->value]],
        'media' => [['upload_token' => coverParityVideo($this)->upload_token, 'meta' => ['cover_image' => ['url' => 'https://example.com/cover.png']]]],
    ];

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.batch.store'), $payload())->assertCreated();
    TryPostServer::actingAs($this->user)->tool(CreatePostsTool::class, $payload())->assertOk();

    [$apiPost, $mcpPost] = coverParityPosts($this);

    expect(coverParityStored($apiPost))->toEqual(coverParityStored($mcpPost))
        ->and(coverParityStored($apiPost)['cover'])->not->toBeNull();
});

test('a Cover set on update is stored the same through api and mcp', function () {
    $posts = collect([0, 1])->map(function (): Post {
        $video = coverParityVideo($this);
        $post = Post::factory()->forAccount($this->instagram, ContentType::InstagramReel)->create(['user_id' => $this->user->id]);
        $row = Media::factory()->video()->ownedByPost($post)->stored()->create();
        $post->update(['media' => [MediaItem::fromMedia($row)->toArray()]]);
        $video->delete();

        return $post;
    });
    $update = fn (Post $post): array => ['media' => [['id' => data_get($post->media, '0.id'), 'meta' => ['cover_image' => ['upload_token' => coverParityImage($this)->upload_token]]]]];

    $this->withHeaders(parityApi($this->token))->putJson(route('api.posts.update', $posts[0]), $update($posts[0]))->assertOk();
    TryPostServer::actingAs($this->user)->tool(UpdatePostTool::class, ['post_id' => $posts[1]->id, ...$update($posts[1])])->assertOk();

    expect(coverParityStored($posts[0]))->toEqual(coverParityStored($posts[1]))
        ->toEqual(['cover' => ['type' => 'image', 'mime_type' => 'image/jpeg'], 'owned' => 2, 'frame' => null]);
});

dataset('refused covers', [
    'a file that is not JPEG or PNG' => fn (object $test): array => ['cover' => ['upload_token' => coverParityImage($test, ['mime_type' => 'image/gif', 'original_filename' => 'cover.gif'])->upload_token], 'error' => __('rxdesk.cover.errors.type')],
    'a file over 8 MB' => fn (object $test): array => ['cover' => ['upload_token' => coverParityImage($test, ['size' => CoverImage::MAX_BYTES + 1])->upload_token], 'error' => __('rxdesk.cover.errors.too_large', ['max' => 8])],
    'a video' => fn (object $test): array => ['cover' => ['upload_token' => coverParityVideo($test)->upload_token], 'error' => __('rxdesk.cover.errors.type')],
    'an expired token' => fn (object $test): array => ['cover' => ['upload_token' => (string) Illuminate\Support\Str::uuid()], 'error' => __('posts.errors.media_expired')],
    'a url that is not an image' => fn (object $test): array => ['cover' => ['url' => 'https://example.com/page.html'], 'error' => __('posts.composer.media_sources.errors.type_not_allowed')],
    'an unreachable url' => fn (object $test): array => ['cover' => ['url' => 'https://example.com/missing.png'], 'error' => __('posts.errors.media_url_unreachable', ['url' => 'https://example.com/missing.png'])],
    'several references' => fn (object $test): array => ['cover' => ['url' => 'https://example.com/cover.png', 'upload_token' => coverParityImage($test)->upload_token], 'error' => __('rxdesk.cover.errors.reference')],
]);

test('a Cover that cannot be used is refused the same way through api and mcp', function (Closure $case) {
    ['cover' => $cover, 'error' => $error] = $case($this);
    $payload = coverParityPayload($this, coverParityVideo($this), $cover);

    $response = $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.store'), $payload)->assertUnprocessable();
    TryPostServer::actingAs($this->user)->tool(CreatePostTool::class, $payload)->assertHasErrors([$error]);

    expect(collect($response->json('errors'))->flatten()->all())->toContain($error)
        ->and(Post::query()->where('workspace_id', $this->workspace->id)->count())->toBe(0);
})->with('refused covers');

test('a Cover on a content type that cannot take one is refused the same way through api and mcp', function (string $type) {
    $payload = coverParityPayload($this, coverParityVideo($this), ['upload_token' => coverParityImage($this)->upload_token], $type);
    $payload['social_account_id'] = $type === ContentType::InstagramStory->value ? $this->instagram->id : SocialAccount::factory()->linkedin()->create(['workspace_id' => $this->workspace->id])->id;
    $error = __('rxdesk.cover.errors.not_supported');

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media.0.meta.cover_image']);
    TryPostServer::actingAs($this->user)->tool(CreatePostTool::class, $payload)->assertHasErrors([$error]);

    expect(Post::query()->where('workspace_id', $this->workspace->id)->count())->toBe(0);
})->with([ContentType::InstagramStory->value, ContentType::LinkedInPost->value]);

test('a Cover on a video that is not alone in the post is refused the same way through api and mcp', function () {
    $payload = coverParityPayload($this, coverParityVideo($this), ['upload_token' => coverParityImage($this)->upload_token], ContentType::InstagramFeed->value);
    $payload['media'][] = ['upload_token' => coverParityVideo($this)->upload_token];

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media.0.meta.cover_image']);
    TryPostServer::actingAs($this->user)->tool(CreatePostTool::class, $payload)->assertHasErrors([__('rxdesk.cover.errors.not_supported')]);
});

/**
 * An empty Instagram Reel draft to attach a video to.
 */
function coverParityReelPost(object $test): Post
{
    return Post::factory()->forAccount($test->instagram, ContentType::InstagramReel)->create(['user_id' => $test->user->id]);
}

test('attaching a video with a Cover from an upload gives the same post through api and mcp', function () {
    $posts = [coverParityReelPost($this), coverParityReelPost($this)];
    $payload = fn (): array => [
        'upload_token' => coverParityVideo($this)->upload_token,
        'meta' => ['cover_image' => ['url' => 'https://example.com/cover.png']],
    ];

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.attach-media-from-upload', $posts[0]), $payload())->assertSuccessful();
    TryPostServer::actingAs($this->user)->tool(AttachMediaFromUploadTool::class, ['post_id' => $posts[1]->id, ...$payload()])->assertOk();

    expect(coverParityStored($posts[0]))->toEqual(coverParityStored($posts[1]))
        ->toEqual(['cover' => ['type' => 'image', 'mime_type' => 'image/png'], 'owned' => 2, 'frame' => null]);
});

test('attaching a video from a url with a Cover gives the same post through api and mcp', function () {
    Http::fake(['example.com/clip.mp4' => fn () => Http::response(file_get_contents(base_path('tests/fixtures/sample.mp4')), 200, ['Content-Type' => 'video/mp4'])]);
    $posts = [coverParityReelPost($this), coverParityReelPost($this)];
    $payload = fn (): array => ['urls' => [['url' => 'https://example.com/clip.mp4', 'meta' => ['cover_image' => ['upload_token' => coverParityImage($this)->upload_token]]]]];

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.attach-media-from-url', $posts[0]), $payload())->assertSuccessful();
    TryPostServer::actingAs($this->user)->tool(AttachMediaFromUrlTool::class, ['post_id' => $posts[1]->id, ...$payload()])->assertOk();

    expect(coverParityStored($posts[0]))->toEqual(coverParityStored($posts[1]))
        ->toEqual(['cover' => ['type' => 'image', 'mime_type' => 'image/jpeg'], 'owned' => 2, 'frame' => null]);
});

test('attaching a Cover to a post that cannot take one is refused the same way through api and mcp', function () {
    $post = Post::factory()->forAccount(SocialAccount::factory()->linkedin()->create(['workspace_id' => $this->workspace->id]), ContentType::LinkedInPost)->create(['user_id' => $this->user->id]);
    $payload = fn (): array => [
        'upload_token' => coverParityVideo($this)->upload_token,
        'meta' => ['cover_image' => ['upload_token' => coverParityImage($this)->upload_token]],
    ];

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.attach-media-from-upload', $post), $payload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media.0.meta.cover_image']);
    TryPostServer::actingAs($this->user)->tool(AttachMediaFromUploadTool::class, ['post_id' => $post->id, ...$payload()])
        ->assertHasErrors([__('rxdesk.cover.errors.not_supported')]);

    expect($post->fresh()->media)->toBeEmpty();
});

test('attaching a Cover that is not a JPEG or PNG is refused the same way through api and mcp', function () {
    $post = coverParityReelPost($this);
    $payload = fn (): array => [
        'upload_token' => coverParityVideo($this)->upload_token,
        'meta' => ['cover_image' => ['upload_token' => coverParityImage($this, ['mime_type' => 'image/gif'])->upload_token]],
    ];

    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.attach-media-from-upload', $post), $payload())->assertUnprocessable();
    TryPostServer::actingAs($this->user)->tool(AttachMediaFromUploadTool::class, ['post_id' => $post->id, ...$payload()])
        ->assertHasErrors([__('rxdesk.cover.errors.type')]);

    expect($post->fresh()->media)->toBeEmpty();
});

test('reading a post shows its Cover through api and mcp', function () {
    $this->withHeaders(parityApi($this->token))->postJson(route('api.posts.store'), coverParityPayload($this, coverParityVideo($this), ['upload_token' => coverParityImage($this)->upload_token]))->assertCreated();
    $post = coverParityPosts($this)->sole();

    $api = $this->withHeaders(parityApi($this->token))->getJson(route('api.posts.show', $post))->assertOk()->json();
    $mcp = null;
    TryPostServer::actingAs($this->user)->tool(GetPostTool::class, ['post_id' => $post->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$mcp) {
            $mcp = $json->toArray();
            $json->etc();
        });

    foreach ([$api, $mcp] as $read) {
        expect(data_get($read, 'media.0.meta.cover_image.type'))->toBe('image')
            ->and(data_get($read, 'media.0.meta.cover_image.id'))->toBe(data_get($post->fresh()->media, '0.meta.cover_image.id'));
    }
});

test('reading a post shows the outcome of its Cover through api and mcp', function () {
    $post = coverParityReelPost($this);
    App\Models\CoverOutcome::factory()->create(['post_id' => $post->id, 'outcome' => App\Enums\Cover\Outcome::Failed]);

    $api = $this->withHeaders(parityApi($this->token))->getJson(route('api.posts.show', $post))->assertOk()->json();
    $mcp = null;
    TryPostServer::actingAs($this->user)->tool(GetPostTool::class, ['post_id' => $post->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$mcp) {
            $mcp = $json->toArray();
            $json->etc();
        });

    expect(data_get($api, 'cover_outcome'))->toBe(['outcome' => 'failed'])
        ->and(data_get($mcp, 'cover_outcome'))->toBe(['outcome' => 'failed']);
});

test('a post without a recorded Cover outcome leaves cover_outcome out', function () {
    $post = coverParityReelPost($this);

    $this->withHeaders(parityApi($this->token))->getJson(route('api.posts.show', $post))->assertOk()->assertJsonMissingPath('cover_outcome');
});

test('the content type listing says which types take a Cover image through api and mcp', function () {
    $api = $this->withHeaders(parityApi($this->token))->getJson(route('api.content-types'))->assertOk()->json('platforms');
    $mcp = null;
    TryPostServer::actingAs($this->user)->tool(ListContentTypesTool::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$mcp) {
            $mcp = $json->toArray()['platforms'];
            $json->etc();
        });

    foreach ([$api, $mcp] as $platforms) {
        $type = fn (string $platform, string $value): array => collect(collect($platforms)->firstWhere('platform', $platform)['content_types'])->firstWhere('value', $value);

        expect($type('instagram', 'instagram_reel'))->toMatchArray(['supports_cover_image' => true, 'cover_image_max_bytes' => CoverImage::MAX_BYTES, 'cover_image_mime_types' => CoverImage::MIME_TYPES])
            ->and($type('instagram', 'instagram_story')['supports_cover_image'])->toBeFalse()
            ->and($type('linkedin', 'linkedin_post')['supports_cover_image'])->toBeFalse();
    }
});

test('the tools that take media describe the Cover image', function () {
    $description = fn (string $class): string => (new ReflectionClass($class))->getAttributes(Laravel\Mcp\Server\Attributes\Description::class)[0]->newInstance()->value;
    $schemaText = function (string $class): string {
        $schema = new Illuminate\JsonSchema\JsonSchemaTypeFactory;

        return json_encode(collect((new $class)->schema($schema))->map(fn ($type) => $type->toArray())->all(), JSON_THROW_ON_ERROR);
    };

    expect($description(ListContentTypesTool::class))->toContain('supports_cover_image', 'meta.cover_image');

    foreach ([CreatePostTool::class, CreatePostsTool::class, UpdatePostTool::class, AttachMediaFromUploadTool::class, AttachMediaFromUrlTool::class] as $tool) {
        expect($schemaText($tool))->toContain('cover_image');
    }
});
