<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The order-confirmation email a Thai checkout gets. It carries the same fixed
 * Thai purchase document the confirmation page shows. Thai is not a UI locale:
 * its text is the one exception to the 16-locale rule, built by
 * `App\Support\Billing\ThaiPurchaseDocument`, so it lives in the document and
 * the Maizzle template, never in `rxdesk.php`.
 */
class ThaiPurchaseDocumentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{
     *     title: string,
     *     rows: list<array{label: string, value: string}>,
     *     cancellation: string
     * }  $document
     */
    public function __construct(public array $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'เอกสารการซื้อสินค้าและบริการ – The RX Desk',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.thai-purchase-document',
            with: [
                'title' => $this->document['title'],
                'previewText' => $this->document['title'],
                'document' => $this->document,
            ],
        );
    }
}
