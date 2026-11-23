<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use App\Services\Ai\AiModelRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAiSettingsRequest extends FormRequest
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
            'tokens_per_credit' => ['sometimes', 'integer', 'min:1'],
            'trial_ai_credits' => ['sometimes', 'integer', 'min:0'],
            'text_model' => ['sometimes', 'nullable', 'string'],
            'image_model' => ['sometimes', 'nullable', 'string'],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A model sent to a slot must be selectable for it. A null clears the slot
     * back to the Config default, which the action checks too.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $registry = app(AiModelRegistry::class);

            $text = $this->input('text_model');

            if ($this->has('text_model') && filled($text) && ! $registry->isSelectable(AiModelRegistry::SLOT_TEXT, (string) $text)) {
                $validator->errors()->add('text_model', 'That model is not available for the text slot.');
            }

            $image = $this->input('image_model');

            if ($this->has('image_model') && filled($image) && ! $registry->isSelectable(AiModelRegistry::SLOT_IMAGE, (string) $image)) {
                $validator->errors()->add('image_model', 'That model is not available for the image slot.');
            }
        }];
    }
}
