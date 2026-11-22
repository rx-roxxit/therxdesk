<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The slug never changes after a Plan is created.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:255'],
            'quota_bytes' => ['sometimes', 'integer', 'min:1'],
            'ai_credits_per_month' => ['sometimes', 'integer', 'min:1'],
            'monthly_amount' => ['sometimes', 'integer', 'min:1', 'max:99999999'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
