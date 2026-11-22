<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOperatorRequest extends FormRequest
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
            'legal_name_th' => ['required', 'string', 'max:255'],
            'legal_name_latin' => ['required', 'string', 'max:255'],
            'address_th' => ['required', 'string', 'max:1000'],
            'address_latin' => ['required', 'string', 'max:1000'],
            'tax_id' => ['required', 'digits:13'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'effective_from' => ['sometimes', 'date', 'before_or_equal:now'],
        ];
    }
}
