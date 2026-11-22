<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Auth;

use App\Enums\User\Locale;
use App\Enums\User\TimeFormat;
use App\Enums\User\WeekStart;
use App\Models\Invite;
use App\Models\User;
use App\Support\Billing\BlockedCountries;
use App\Support\Countries;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
            'locale' => ['required', Rule::enum(Locale::class)],
            'timezone' => ['nullable', 'string'],
            'week_starts_on' => ['nullable', Rule::enum(WeekStart::class)],
            'time_format' => ['nullable', Rule::enum(TimeFormat::class)],
        ];

        if (config('trypost.self_hosted')) {
            $rules['country'] = ['nullable', 'string', 'size:2', Rule::in(Countries::ISO)];

            return $rules;
        }

        $rules['country'] = [
            'required',
            'string',
            'size:2',
            Rule::in(Countries::ISO),
            function (string $attribute, mixed $value, Closure $fail): void {
                if (BlockedCountries::isBlocked(is_string($value) ? $value : null)) {
                    $fail(__('rxdesk.country.blocked'));
                }
            },
        ];

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $country = $this->input('country');

        if (! is_string($country)) {
            return;
        }

        $this->merge(['country' => Str::upper(trim($country))]);
    }

    public function invite(): ?Invite
    {
        return Invite::fromId($this->string('invite')->toString());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invite = $this->invite();

            if ($invite && ! $invite->isFor($this->string('email')->toString())) {
                $validator->errors()->add(
                    'email',
                    __('settings.members.flash.wrong_email'),
                );
            }
        });
    }
}
