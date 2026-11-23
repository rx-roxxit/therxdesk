<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Seven days before the data is deleted, the Owner is told the date and that
 * an export can be requested.
 */
class DeletionIn7Days extends LifecycleMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('rxdesk.lifecycle.deletion_in_7_days.subject'));
    }

    public function content(): Content
    {
        $recipient = $this->account->owner;

        return new Content(
            view: 'mail.lifecycle-deletion-7-days',
            with: [
                'title' => __('rxdesk.lifecycle.deletion_in_7_days.title'),
                'previewText' => __('rxdesk.lifecycle.deletion_in_7_days.preview'),
                'deletionDate' => $recipient !== null
                    ? $this->localDate($this->account->deletion_scheduled_at, $recipient)
                    : null,
                'planUrl' => route('app.welcome.plan'),
                'supportUrl' => $this->supportUrl(),
            ],
        );
    }
}
