<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Ai\Agents\MediaAltTextGenerator;
use App\Enums\Ai\AiFeature;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use App\Support\PostMediaRules;
use Laravel\Ai\Files\Image;

final class GenerateMediaAltText
{
    public static function execute(User $user, Media $asset, Workspace $workspace): string
    {
        app(AiSettings::class)->apply(AiFeature::AltText);

        $response = (new MediaAltTextGenerator($user->locale))->prompt(
            'Write the alt text for this image.',
            attachments: [Image::fromStorage($asset->path)],
        );

        app(AiCredits::class)->charge(
            $workspace->account,
            $user,
            $workspace,
            AiFeature::AltText,
            $response->usage,
            $response->meta->model,
        );

        return mb_substr(trim((string) $response), 0, PostMediaRules::ALT_TEXT_MAX_LENGTH);
    }
}
