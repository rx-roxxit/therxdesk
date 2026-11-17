<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Auth;

use App\Enums\User\Locale;
use App\Enums\User\TimeFormat;
use App\Enums\User\WeekStart;
use App\Models\Invite;
use App\Models\SignupCode;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
            'locale' => ['required', Rule::enum(Locale::class)],
            'timezone' => ['nullable', 'string'],
            'week_starts_on' => ['nullable', Rule::enum(WeekStart::class)],
            'time_format' => ['nullable', Rule::enum(TimeFormat::class)],
            'signup_code' => [Rule::requiredIf(fn (): bool => $this->requiresSignupCode()), 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signup_code.required' => __('rxdesk.signup_code.errors.required'),
        ];
    }

    public function requiresSignupCode(): bool
    {
        return SignupCode::isRequiredFor($this->invite());
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

            if ($this->requiresSignupCode() && filled($this->input('signup_code')) && ! $validator->errors()->has('signup_code')) {
                $refusal = SignupCode::refusalFor(SignupCode::query()->withCode($this->string('signup_code')->toString())->first());

                if (filled($refusal)) {
                    $validator->errors()->add('signup_code', $refusal);
                }
            }
        });
    }
}
