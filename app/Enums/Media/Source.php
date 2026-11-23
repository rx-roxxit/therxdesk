<?php

declare(strict_types=1);

namespace App\Enums\Media;

use App\Enums\Availability\ServiceSwitch;

/**
 * Origin of a media attachment on a post. `null`/absent means "uploaded by
 * the user". The picker sources are the ones offered in the composer's media
 * menu; each is exposed only when its keys are set and its Availability
 * switch is on.
 * `GoogleDriveBackup` is reserved for the Owner's Video policy backup: an
 * offline sign-in kept apart from the picker, never offered in the composer.
 */
enum Source: string
{
    case Ai = 'ai';
    case Unsplash = 'unsplash';
    case GoogleDrive = 'google_drive';
    case GooglePhotos = 'google_photos';
    case Canva = 'canva';
    case GoogleDriveBackup = 'google_drive_backup';

    /** @return list<self> */
    public static function pickerSources(): array
    {
        return [self::Canva, self::GoogleDrive, self::GooglePhotos, self::Unsplash];
    }

    /**
     * Whether the server has the credentials the source needs. The switch
     * only decides whether an installed source is offered.
     */
    public function isInstalled(): bool
    {
        return match ($this) {
            self::Unsplash => filled(config('services.unsplash.access_key')),
            self::GoogleDrive => (bool) config('trypost.media_sources.google_drive.enabled')
                && self::googleOAuthConfigured()
                && filled(config('services.google_media.api_key'))
                && filled(config('services.google_media.app_id')),
            self::GooglePhotos => (bool) config('trypost.media_sources.google_photos.enabled')
                && self::googleOAuthConfigured(),
            self::Canva => (bool) config('trypost.media_sources.canva.enabled')
                && filled(config('services.canva.client_id'))
                && filled(config('services.canva.client_secret')),
            self::GoogleDriveBackup => filled(config('services.google_media.client_id'))
                && filled(config('services.google_media.client_secret'))
                && filled(config('services.google_media.backup_redirect')),
            self::Ai => false,
        };
    }

    public function isEnabled(): bool
    {
        if (! $this->isInstalled()) {
            return false;
        }

        // The backup connection is not a picker: its service switch gates the
        // Video policy and the job, never the sign-in that keeps Retry working.
        if ($this === self::GoogleDriveBackup) {
            return true;
        }

        $switch = $this->serviceSwitch();

        if ($switch === null) {
            return false;
        }

        return $switch->switchedOn();
    }

    public function serviceSwitch(): ?ServiceSwitch
    {
        return match ($this) {
            self::Unsplash => ServiceSwitch::Unsplash,
            self::GoogleDrive => ServiceSwitch::DrivePicker,
            self::GooglePhotos => ServiceSwitch::GooglePhotos,
            self::Canva => ServiceSwitch::Canva,
            self::GoogleDriveBackup, self::Ai => null,
        };
    }

    /**
     * Google sign-in for Drive and Photos runs server-side, so the OAuth
     * client needs its secret and the redirect URI registered with Google.
     */
    private static function googleOAuthConfigured(): bool
    {
        return filled(config('services.google_media.client_id'))
            && filled(config('services.google_media.client_secret'))
            && filled(config('services.google_media.redirect'));
    }

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'AI',
            self::Unsplash => 'Unsplash',
            self::GoogleDrive => 'Google Drive',
            self::GooglePhotos => 'Google Photos',
            self::Canva => 'Canva',
            self::GoogleDriveBackup => 'Google Drive backup',
        };
    }

    /** @return list<string> */
    public function downloadHosts(): array
    {
        $hosts = match ($this) {
            self::Unsplash, self::GoogleDrive, self::GooglePhotos, self::Canva => config("trypost.media_sources.{$this->value}.download_hosts"),
            default => '',
        };

        return array_values(array_filter(array_map('trim', explode(',', (string) $hosts))));
    }

    /**
     * Hosts Canva's design `edit_url` may point at.
     *
     * @return list<string>
     */
    public function editorHosts(): array
    {
        $hosts = $this === self::Canva ? config('trypost.media_sources.canva.editor_hosts') : '';

        return array_values(array_filter(array_map('trim', explode(',', (string) $hosts))));
    }

    /**
     * Public identifiers only; secrets never leave the server.
     *
     * @return array<string, string>
     */
    public function publicConfig(): array
    {
        return match ($this) {
            self::GoogleDrive => [
                'api_key' => (string) config('services.google_media.api_key'),
                'app_id' => (string) config('services.google_media.app_id'),
                'picker_sdk' => config('trypost.media_sources.google_drive.picker_sdk'),
            ],
            default => [],
        };
    }

    /** @return list<array{source: string, label: string, config: array<string, string>, presets?: list<array{value: string, width: int, height: int, is_default: bool}>}> */
    public static function menu(): array
    {
        return collect(self::pickerSources())
            ->filter(fn (self $source): bool => $source->isEnabled())
            ->map(fn (self $source): array => [
                'source' => $source->value,
                'label' => $source->label(),
                'config' => $source->publicConfig(),
                ...($source === self::Canva ? ['presets' => CanvaPreset::options()] : []),
            ])
            ->values()
            ->all();
    }
}
