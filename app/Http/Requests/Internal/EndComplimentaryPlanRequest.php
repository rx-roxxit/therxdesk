<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EndComplimentaryPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An earlier date than now is allowed: it ends the Complimentary Plan at
     * once. 2037-12-31 is the MySQL far-future sentinel.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ends_at' => ['nullable', 'date', 'before_or_equal:2037-12-31'],
        ];
    }
}
