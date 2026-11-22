<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the fields a running campaign may still change. Discount, duration,
     * coverage and reach are locked, and `slug` is the link's identity, so
     * sending any of them is refused here rather than silently ignored.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => ['prohibited'],
            'reach' => ['prohibited'],
            'coupon' => ['prohibited'],
            'discount_type' => ['prohibited'],
            'percent_off' => ['prohibited'],
            'amount_off' => ['prohibited'],
            'duration' => ['prohibited'],
            'duration_in_months' => ['prohibited'],
            'plan_ids' => ['prohibited'],
            'intervals' => ['prohibited'],
            'stripe_coupon_id' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'max_redemptions' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'show_banner' => ['sometimes', 'boolean'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
