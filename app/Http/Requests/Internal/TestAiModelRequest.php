<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use App\Services\Ai\AiModelRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TestAiModelRequest extends FormRequest
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
            'slot' => ['required', 'string', Rule::in([AiModelRegistry::SLOT_TEXT, AiModelRegistry::SLOT_IMAGE])],
            'model' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $slot = (string) $this->input('slot');
            $model = (string) $this->input('model');

            if (! app(AiModelRegistry::class)->isSelectable($slot, $model)) {
                $validator->errors()->add('model', 'That model is not available for this slot.');
            }
        }];
    }
}
