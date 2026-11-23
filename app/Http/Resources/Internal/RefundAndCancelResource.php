<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The result of the admin app's Refund and cancel: the refunded charge, the
 * Stripe refund, the cancelled Subscription and the Account's access. No
 * secret is ever included.
 *
 * @mixin array<string, mixed>
 */
class RefundAndCancelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoice_id' => $this->resource['invoice_id'],
            'amount_paid' => $this->resource['amount_paid'],
            'currency' => $this->resource['currency'],
            'refund_id' => $this->resource['refund_id'],
            'subscription' => [
                'id' => $this->resource['subscription_id'],
                'status' => $this->resource['status'],
                'ends_at' => $this->resource['ends_at'],
            ],
            'cancellation_type' => 'admin',
            'has_app_access' => $this->resource['has_app_access'],
        ];
    }
}
