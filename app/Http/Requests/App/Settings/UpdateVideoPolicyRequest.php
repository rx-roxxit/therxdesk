<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Settings;

use App\Enums\Account\VideoPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Owner's Video policy, saved on its own like the other preferences.
 * Only the account's Owner may change it.
 */
class UpdateVideoPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->user()->account;

        if ($account === null) {
            return false;
        }

        return $this->user()->can('update', $account);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'video_policy' => ['required', Rule::enum(VideoPolicy::class)->only(VideoPolicy::selectable($this->user()->account->driveConnection()))],
        ];
    }
}
