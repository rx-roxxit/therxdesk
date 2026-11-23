<?php

declare(strict_types=1);

namespace App\Support\SupportChat;

use App\Enums\Availability\ServiceSwitch;
use App\Support\Availability\Availability;

/**
 * Only this gate decides whether the chat exists; #64 wires the service
 * switches to it.
 */
final class SupportChatGate
{
    public const string AVAILABILITY_KEY = 'service:support_chat';

    public const string AI_AVAILABILITY_KEY = 'service:ai';

    public function __construct(private readonly Availability $availability) {}

    public function enabled(): bool
    {
        if ((bool) config('trypost.self_hosted')) {
            return false;
        }

        if (! ServiceSwitch::aiInstalled()) {
            return false;
        }

        if (! $this->availability->isOn(self::AI_AVAILABILITY_KEY)) {
            return false;
        }

        return $this->availability->isOn(self::AVAILABILITY_KEY, default: false);
    }
}
