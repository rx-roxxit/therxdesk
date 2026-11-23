<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Auth;

use App\Enums\Availability\ServiceSwitch;
use App\Enums\User\Locale;
use App\Enums\User\TimeFormat;
use App\Enums\User\WeekStart;
use App\Models\Invite;
use App\Models\User;
use App\Rules\TurnstileToken;
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
        $turnstileRules = [new TurnstileToken($this->ip())];

        if (filled(config('rxdesk.turnstile.secret_key'))) {
            array_unshift($turnstileRules, 'required');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
            'locale' => ['required', Rule::enum(Locale::class)],
            'timezone' => ['nullable', 'string'],
            'week_starts_on' => ['nullable', Rule::enum(WeekStart::class)],
            'time_format' => ['nullable', Rule::enum(TimeFormat::class)],
            'cf-turnstile-response' => $turnstileRules,
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

    /**
     * The missing field is the widget's own refusal, so it says the same
     * thing as an invalid token.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cf-turnstile-response.required' => __('rxdesk.turnstile.failed'),
        ];
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

            if (ServiceSwitch::newSignupsClosed() && $invite === null) {
                $validator->errors()->add('email', __('rxdesk.signup.closed'));
            }

            if ($invite && ! $invite->isFor($this->string('email')->toString())) {
                $validator->errors()->add(
                    'email',
                    __('settings.members.flash.wrong_email'),
                );
            }
        });
    }
}
