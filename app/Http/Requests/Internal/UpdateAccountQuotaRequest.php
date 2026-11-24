<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The field is always sent: null clears the override and 0 is a valid
     * Quota, so the Account may store nothing more.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quota_bytes' => ['present', 'nullable', 'integer', 'min:0'],
        ];
    }
}
