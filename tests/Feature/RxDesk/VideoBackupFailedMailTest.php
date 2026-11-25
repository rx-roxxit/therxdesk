<?php

declare(strict_types=1);

use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Enums\User\Locale;
use App\Enums\VideoRecord\Outcome;
use App\Mail\VideoBackupFailed;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\VideoRecord;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create(['name' => 'Acme Store']);
    $channel = SocialAccount::factory()->telegram()->create(['workspace_id' => $this->workspace->id]);
    $this->post = Post::factory()->forAccount($channel)->create([
        'user_id' => $this->workspace->account->owner_id,
        'status' => PostStatus::Published,
        'publish_status' => PublishStatus::Published,
        'published_at' => now(),
    ]);
    VideoRecord::factory()->create([
        'post_id' => $this->post->id,
        'original_filename' => 'launch.mp4',
        'outcome' => Outcome::BackupFailed,
    ]);
});

test('the backup failed email names the workspace and the file, and links to the post', function () {
    $mail = new VideoBackupFailed($this->post);

    $mail->assertHasSubject('A video backup failed in Acme Store');
    $mail->assertSeeInHtml('Video backup failed');
    $mail->assertSeeInHtml('Acme Store');
    $mail->assertSeeInHtml('launch.mp4');
    $mail->assertSeeInHtml('The video is still on the server.');
    $mail->assertSeeInHtml(e(route('app.posts.index', ['tab' => 'sent', 'post' => $this->post->id])), false);
    $mail->assertDontSeeInHtml('rxdesk.video_backup_failed');
    $mail->assertDontSeeInHtml('Reconnect Google Drive');
});

test('the email asks to reconnect Drive only when Google rejected the connection', function () {
    $mail = new VideoBackupFailed($this->post, needsReconnect: true);

    $mail->assertSeeInHtml('Google rejected the Drive connection.');
    $mail->assertSeeInHtml('Reconnect Google Drive');
    $mail->assertSeeInHtml(e(route('app.settings.preferences')), false);
});

test('the email is written in the language the recipient picked', function (Locale $locale) {
    $mail = (new VideoBackupFailed($this->post))->locale($locale->value);

    $mail->assertHasSubject(__('rxdesk.video_backup_failed.subject', ['workspace' => 'Acme Store'], $locale->value));
    $mail->assertSeeInHtml(__('rxdesk.video_backup_failed.next_steps', [], $locale->value));
})->with(Locale::cases());
