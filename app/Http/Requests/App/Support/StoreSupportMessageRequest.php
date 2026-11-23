<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Support;

use App\Support\SupportChat\SupportChatLimits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportMessageRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:'.SupportChatLimits::MAX_CHARACTERS],
            'page' => ['nullable', 'string', 'max:120', 'starts_with:/'],
        ];
    }
}
