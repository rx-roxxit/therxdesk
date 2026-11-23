<?php

declare(strict_types=1);

namespace App\Enums\Availability;

use App\Enums\Media\Source;
use App\Support\Availability\Availability;
use App\Support\SupportChat\SupportChatGate;

/**
 * The services and the signup door the System admin switches in Availability.
 * Each key is stored in `availability_switches`; a missing row counts as on,
 * except Support chat and new signups, which start off on deploy day.
 */
enum ServiceSwitch: string
{
    case Ai = 'service:ai';
    case SupportChat = 'service:support_chat';
    case DriveBackup = 'service:drive_backup';
    case DrivePicker = 'service:drive_picker';
    case Canva = 'service:canva';
    case GooglePhotos = 'service:google_photos';
    case Unsplash = 'service:unsplash';
    case NewSignups = 'new_signups';

    /**
     * How the admin app groups the switch: a service, or the signup door.
     */
    public function kind(): string
    {
        return $this === self::NewSignups ? 'signups' : 'service';
    }

    /**
     * The key without its `service:` prefix; the key itself for signups.
     */
    public function item(): string
    {
        return match ($this) {
            self::Ai => 'ai',
            self::SupportChat => 'support_chat',
            self::DriveBackup => 'drive_backup',
            self::DrivePicker => 'drive_picker',
            self::Canva => 'canva',
            self::GooglePhotos => 'google_photos',
            self::Unsplash => 'unsplash',
            self::NewSignups => 'new_signups',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'AI',
            self::SupportChat => 'Support chat',
            self::DriveBackup => 'Drive backup',
            self::DrivePicker => 'Drive picker',
            self::Canva => 'Canva',
            self::GooglePhotos => 'Google Photos',
            self::Unsplash => 'Unsplash',
            self::NewSignups => 'New signups',
        };
    }

    public function defaultOn(): bool
    {
        return match ($this) {
            self::SupportChat, self::NewSignups => false,
            default => true,
        };
    }

    /**
     * Whether the server has what the item needs to work at all: the AI
     * provider key, the Drive or Canva credentials, an Unsplash key. Support
     * chat also needs selling mode, because it does not exist self-hosted.
     */
    public function isInstalled(): bool
    {
        return match ($this) {
            self::Ai => self::aiInstalled(),
            self::SupportChat => ! config('trypost.self_hosted') && self::aiInstalled(),
            self::DriveBackup => Source::GoogleDriveBackup->isInstalled(),
            self::DrivePicker => Source::GoogleDrive->isInstalled(),
            self::Canva => Source::Canva->isInstalled(),
            self::GooglePhotos => Source::GooglePhotos->isInstalled(),
            self::Unsplash => Source::Unsplash->isInstalled(),
            self::NewSignups => true,
        };
    }

    public function switchedOn(): bool
    {
        return app(Availability::class)->isOn($this->value, $this->defaultOn());
    }

    /**
     * The item actually works: it is installed and switched on. Support chat
     * also needs the AI switch, so turning AI off takes the chat down too.
     */
    public function effective(): bool
    {
        if ($this === self::SupportChat) {
            return app(SupportChatGate::class)->enabled();
        }

        return $this->isInstalled() && $this->switchedOn();
    }

    /**
     * New signups are closed in selling mode while their switch is off; a
     * self-hosted install keeps upstream's registration rules.
     */
    public static function newSignupsClosed(): bool
    {
        if (config('trypost.self_hosted')) {
            return false;
        }

        return ! app(Availability::class)->isOn(self::NewSignups->value, self::NewSignups->defaultOn());
    }

    public static function aiInstalled(): bool
    {
        return filled(config('ai.providers.'.config('ai.default').'.key'));
    }
}
