<?php

declare(strict_types=1);

use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Enums\VideoRecord\Outcome;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\VideoRecord;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, membershipPivot('admin'));
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $channel = SocialAccount::factory()->telegram()->create(['workspace_id' => $this->workspace->id]);
    $this->post = Post::factory()->forAccount($channel)->create([
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
        'publish_status' => PublishStatus::Published,
        'published_at' => now()->subHours(2),
    ]);
    VideoRecord::factory()->create([
        'post_id' => $this->post->id,
        'original_filename' => 'launch.mp4',
        'size' => 2_000_000,
        'last_error' => 'internal detail',
    ]);
});

test('the sent tab card carries the video deleted line', function () {
    $this->actingAs($this->user)
        ->get(route('app.posts.index', ['tab' => 'sent']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('posts.data.0.id', $this->post->id)
                ->has('posts.data.0.video_records', 1)
                ->where('posts.data.0.video_records.0.outcome', 'deleted')
                ->where('posts.data.0.video_records.0.original_filename', 'launch.mp4')
                ->missing('posts.data.0.video_records.0.last_error')));
});

test('the post details of a group carry the video deleted line', function () {
    $this->actingAs($this->user)
        ->getJson(route('app.posts.group.show', $this->post))
        ->assertOk()
        ->assertJsonPath('0.video_records.0.outcome', 'deleted')
        ->assertJsonPath('0.video_records.0.original_filename', 'launch.mp4');
});

test('the post details carry the video backed up line with its drive link', function () {
    VideoRecord::query()->delete();
    VideoRecord::factory()->create([
        'post_id' => $this->post->id,
        'original_filename' => 'launch.mp4',
        'outcome' => Outcome::BackedUp,
        'drive_file_id' => 'drive-file-1',
        'drive_link' => 'https://drive.google.test/file/d/drive-file-1/view',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('app.posts.group.show', $this->post))
        ->assertOk()
        ->assertJsonPath('0.video_records.0.outcome', 'backed_up')
        ->assertJsonPath('0.video_records.0.drive_link', 'https://drive.google.test/file/d/drive-file-1/view')
        ->assertJsonMissingPath('0.video_records.0.drive_file_id');
});
