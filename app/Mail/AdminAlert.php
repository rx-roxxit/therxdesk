<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one email the alert system sends, for every level. The template resolves
 * the body copy from `rxdesk.php`; this class passes only data and the
 * envelope metadata.
 */
class AdminAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $items
     */
    public function __construct(
        public AlertLevel $level,
        public ?AdminAlertKey $key = null,
        public array $context = [],
        public array $items = [],
        public bool $isResolved = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->level->label()}] The RX Desk: {$this->title()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-alert',
            with: [
                'title' => $this->title(),
                'previewText' => __("rxdesk.alerts.preview.{$this->level->value}", $this->context),
                'level' => $this->level,
                'key' => $this->key,
                'context' => $this->context,
                'items' => $this->items,
                'sentAt' => now()->timezone((string) config('rxdesk.alerts.timezone'))->format('Y-m-d H:i T'),
                'isDigest' => $this->level === AlertLevel::Digest,
                'isResolved' => $this->isResolved,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function title(): string
    {
        if ($this->key !== null) {
            return $this->key->title($this->context);
        }

        return __('rxdesk.alerts.digest.title');
    }
}
