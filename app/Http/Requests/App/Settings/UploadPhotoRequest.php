<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Settings;

use App\Rules\DiskHasRoom;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['bail', 'required', 'image', 'max:2048', new DiskHasRoom],
        ];
    }
}
