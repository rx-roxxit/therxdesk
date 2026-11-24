<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use App\Models\NetworkLimitSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNetworkLimitThresholdsRequest extends FormRequest
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
            'today_percent' => ['sometimes', 'integer', 'between:1,99'],
            'urgent_percent' => ['sometimes', 'integer', 'between:2,100'],
            'account_digest_percent' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $current = NetworkLimitSetting::current();
            $today = (int) $this->input('today_percent', $current->today_percent);
            $urgent = (int) $this->input('urgent_percent', $current->urgent_percent);

            if ($today >= $urgent) {
                $validator->errors()->add('today_percent', 'The urgent threshold must be greater than the today threshold.');
            }
        });
    }
}
