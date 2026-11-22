<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
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
            'slug' => ['required', 'string', 'alpha_dash', 'lowercase', 'max:255', Rule::unique('plans', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'quota_bytes' => ['required', 'integer', 'min:1'],
            'ai_credits_per_month' => ['required', 'integer', 'min:1'],
            'monthly_amount' => ['required', 'integer', 'min:1', 'max:99999999'],
            'sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
