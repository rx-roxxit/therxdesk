<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\Account;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Access stopped: the Account is Lapsed and its data goes 30 days later unless
 * it subscribes again.
 */
class AccessStopped extends LifecycleMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('rxdesk.lifecycle.access_stopped.subject'));
    }

    public function content(): Content
    {
        $recipient = $this->account->owner;

        return new Content(
            view: 'mail.lifecycle-access-stopped',
            with: [
                'title' => __('rxdesk.lifecycle.access_stopped.title'),
                'previewText' => __('rxdesk.lifecycle.access_stopped.preview'),
                'deletionDate' => $recipient !== null
                    ? $this->localDate($this->account->deletion_scheduled_at, $recipient)
                    : null,
                'planUrl' => route('app.welcome.plan'),
                'supportUrl' => $this->supportUrl(),
            ],
        );
    }
}
