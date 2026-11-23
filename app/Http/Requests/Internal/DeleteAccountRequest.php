<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Delete now" asks the System admin to type the Owner's email as the
 * confirmation. The exact check runs in DeleteAccountNow, after the Account
 * and its Owner are loaded.
 */
class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'confirm_email' => ['required', 'string', 'email'],
        ];
    }
}
