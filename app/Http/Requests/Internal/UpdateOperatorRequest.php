<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatorRequest extends FormRequest
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
            'legal_name_th' => ['sometimes', 'string', 'max:255'],
            'legal_name_latin' => ['sometimes', 'string', 'max:255'],
            'address_th' => ['sometimes', 'string', 'max:1000'],
            'address_latin' => ['sometimes', 'string', 'max:1000'],
            'tax_id' => ['sometimes', 'digits:13'],
            'vat_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contact_email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'effective_from' => ['sometimes', 'date', 'before_or_equal:now'],
        ];
    }
}
