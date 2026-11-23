<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Settings;

use App\Enums\Account\VideoPolicy;
use App\Enums\Availability\ServiceSwitch;
use Closure;
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
            'video_policy' => [
                'required',
                Rule::enum(VideoPolicy::class)->only(VideoPolicy::selectable($this->user()->account->driveConnection())),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== VideoPolicy::Backup->value) {
                        return;
                    }

                    if (ServiceSwitch::DriveBackup->switchedOn()) {
                        return;
                    }

                    if ($this->user()->account->video_policy === VideoPolicy::Backup) {
                        return;
                    }

                    $fail(__('rxdesk.video_policy.errors.backup_unavailable'));
                },
            ],
        ];
    }
}
