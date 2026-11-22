<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplimentaryPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Any Plan can be given, an archived one included. The end date is
     * optional and must leave room for the MySQL `timestamp` ceiling, so
     * 2037-12-31 is the far-future sentinel.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')],
            'ends_at' => ['nullable', 'date', 'after:now', 'before_or_equal:2037-12-31'],
        ];
    }
}
