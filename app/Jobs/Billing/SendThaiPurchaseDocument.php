<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Mail\ThaiPurchaseDocumentMail;
use App\Models\Account;
use App\Support\Billing\ThaiPurchaseDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the Thai purchase document a checkout billed to Thailand gets. It is
 * queued shortly after the checkout so the subscription webhook has written
 * the Plan, its price and the Trial's dates; building the document earlier
 * would miss them. A delivery is sent once per Checkout Session: the claim is
 * atomic, and a run that still lacks the settled subscription releases itself
 * to try again.
 */
class SendThaiPurchaseDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public string $accountId,
        public string $sessionId,
    ) {}

    public function handle(): void
    {
        $account = Account::query()->with(['owner', 'plan'])->find($this->accountId);

        if ($account === null) {
            return;
        }

        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);

        if ($account->plan_id === null || $subscription?->stripe_price === null) {
            if ($this->attempts() < $this->tries) {
                $this->release(15);
            }

            return;
        }

        $claimKey = "rxdesk:thai_purchase_document:{$this->sessionId}";

        if (! Cache::add($claimKey, true, now()->addDays(30))) {
            return;
        }

        $document = ThaiPurchaseDocument::for($account);
        $owner = $account->owner;

        if ($document === null || $owner === null) {
            return;
        }

        Mail::to($owner)->send(new ThaiPurchaseDocumentMail($document));
    }
}
