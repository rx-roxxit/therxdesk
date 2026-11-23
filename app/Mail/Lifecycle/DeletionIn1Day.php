<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One day before the data is deleted. The last warning, and the last chance to
 * ask for an export.
 */
class DeletionIn1Day extends LifecycleMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('rxdesk.lifecycle.deletion_in_1_day.subject'));
    }

    public function content(): Content
    {
        $recipient = $this->account->owner;

        return new Content(
            view: 'mail.lifecycle-deletion-1-day',
            with: [
                'title' => __('rxdesk.lifecycle.deletion_in_1_day.title'),
                'previewText' => __('rxdesk.lifecycle.deletion_in_1_day.preview'),
                'deletionDate' => $recipient !== null
                    ? $this->localDate($this->account->deletion_scheduled_at, $recipient)
                    : null,
                'planUrl' => route('app.welcome.plan'),
                'supportUrl' => $this->supportUrl(),
            ],
        );
    }
}
