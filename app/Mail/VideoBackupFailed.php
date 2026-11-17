<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\VideoRecord\Outcome;
use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the Owner that a post's videos could not be backed up to Google Drive
 * and are still on the server. English only (the RX Desk's own copy lives in
 * `lang/<locale>/rxdesk.php`).
 */
class VideoBackupFailed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Post $post,
        public bool $needsReconnect = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('rxdesk.video_backup_failed.subject', ['workspace' => $this->post->workspace->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.video-backup-failed',
            with: [
                'title' => __('rxdesk.video_backup_failed.title'),
                'previewText' => __('rxdesk.video_backup_failed.preview'),
                'workspaceName' => $this->post->workspace->name,
                'fileNames' => $this->post->videoRecords()
                    ->where('outcome', Outcome::BackupFailed)
                    ->pluck('original_filename')
                    ->filter()
                    ->values()
                    ->all(),
                'needsReconnect' => $this->needsReconnect,
                'url' => route('app.posts.index', ['tab' => 'sent', 'post' => $this->post->id]),
                'preferencesUrl' => route('app.settings.preferences'),
            ],
        );
    }
}
