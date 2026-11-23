<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\User\Locale;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Support\SupportChat\SupportBrief;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Support chat's hand-off to the team: the customer's message plus the
 * conversation's context and transcript. It goes to the internal support
 * address synchronously, in English, and replies go straight to the customer.
 */
class SupportHandoff extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportConversation $conversation,
        public string $customerEmail,
        public string $customerMessage,
    ) {
        $this->locale = Locale::English->value;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('rxdesk.support_handoff.subject', [
                'email' => $this->customerEmail,
                'plan' => $this->plan(),
            ]),
            replyTo: [new Address($this->customerEmail)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.support-handoff',
            with: [
                'title' => __('rxdesk.support_handoff.title'),
                'previewText' => __('rxdesk.support_handoff.preview'),
                'customerEmail' => $this->customerEmail,
                'customerMessage' => $this->customerMessage,
                'accountId' => $this->conversation->account_id,
                'plan' => $this->plan(),
                'status' => app(SupportBrief::class)->accountStatus($this->conversation->account),
                'locale' => $this->conversation->user->locale->value,
                'lastPage' => $this->conversation->last_page,
                'transcript' => $this->transcript(),
            ],
        );
    }

    private function plan(): string
    {
        return $this->conversation->account?->plan?->name ?? __('rxdesk.support_handoff.no_plan');
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function transcript(): array
    {
        return $this->conversation->messages()
            ->get()
            ->map(fn (SupportMessage $message): array => [
                'role' => $message->isAnswer()
                    ? __('rxdesk.support_handoff.role_assistant')
                    : __('rxdesk.support_handoff.role_user'),
                'content' => $message->content,
            ])
            ->values()
            ->all();
    }
}
