<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A reviewer or test Account: the email must be unused by any User, the
     * password follows registration's rules, and the Plan may be archived.
     * The optional end date leaves room for the MySQL `timestamp` ceiling, so
     * 2037-12-31 is the far-future sentinel.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Rules\Password::defaults()],
            'plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')],
            'complimentary_until' => ['nullable', 'date', 'after:now', 'before_or_equal:2037-12-31'],
            'review_access' => ['sometimes', 'boolean'],
        ];
    }
}
