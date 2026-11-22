<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use App\Enums\Billing\Interval;
use App\Enums\Billing\OfferDiscountType;
use App\Enums\Billing\OfferDuration;
use App\Enums\Billing\OfferReach;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'alpha_dash', 'lowercase', 'max:100', Rule::unique('offers', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'reach' => ['required', Rule::enum(OfferReach::class)],
            'coupon' => ['nullable', 'array'],
            'coupon.type' => ['required_with:coupon', Rule::enum(OfferDiscountType::class)],
            'coupon.percent_off' => ['nullable', 'required_if:coupon.type,percent', 'numeric', 'decimal:0,2', 'gt:0', 'lte:100'],
            'coupon.amount_off' => ['nullable', 'required_if:coupon.type,amount', 'integer', 'min:1', 'max:99999999'],
            'coupon.duration' => ['required_with:coupon', Rule::enum(OfferDuration::class)],
            'coupon.duration_in_months' => ['nullable', 'required_if:coupon.duration,repeating', 'integer', 'min:1', 'max:60'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'show_banner' => ['boolean'],
            'plan_ids' => ['nullable', 'array', 'min:1'],
            'plan_ids.*' => ['uuid', 'distinct', Rule::exists('plans', 'id')->where('is_archived', false)],
            'intervals' => ['nullable', 'array', 'min:1'],
            'intervals.*' => [Rule::enum(Interval::class), 'distinct'],
        ];
    }

    /**
     * A campaign must do something: a coupon, trial days, or both.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (filled($this->input('coupon'))) {
                return;
            }

            if ((int) $this->input('trial_days', 0) > 0) {
                return;
            }

            $validator->errors()->add('coupon', 'An Offer needs a coupon, trial days, or both.');
        });
    }
}
