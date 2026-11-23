<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\Account;
use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The Member's own warning seven days before a Lapsed Account's data is
 * deleted, so they can save what they need or sign up themselves.
 */
class MemberDeletionIn7Days extends LifecycleMail
{
    public function __construct(
        Account $account,
        public User $recipient,
    ) {
        parent::__construct($account);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('rxdesk.lifecycle.member_deletion_in_7_days.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.lifecycle-member-deletion-7-days',
            with: [
                'title' => __('rxdesk.lifecycle.member_deletion_in_7_days.title'),
                'previewText' => __('rxdesk.lifecycle.member_deletion_in_7_days.preview'),
                'deletionDate' => $this->localDate($this->account->deletion_scheduled_at, $this->recipient),
                'supportUrl' => $this->supportUrl(),
            ],
        );
    }
}
