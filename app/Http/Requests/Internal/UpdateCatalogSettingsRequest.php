<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogSettingsRequest extends FormRequest
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
            'trial_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'tokens_per_credit' => ['sometimes', 'integer', 'min:1'],
            'trial_ai_credits' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
