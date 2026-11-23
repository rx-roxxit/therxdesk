<?php

declare(strict_types=1);

namespace App\Actions\Idea;

use App\Ai\Agents\IdeaGenerator;
use App\Enums\Ai\AiFeature;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use App\Support\AiPromptRules;
use Illuminate\Support\Str;

class GenerateIdea
{
    /**
     * @return array{title: string, body: string}|null
     */
    public static function execute(User $user, string $business, string $audience, ?string $notes, Workspace $workspace): ?array
    {
        app(AiSettings::class)->apply(AiFeature::IdeaGeneration);

        $response = (new IdeaGenerator($user->locale, $business, $audience, $notes))
            ->prompt('Suggest one content idea.');

        app(AiCredits::class)->charge(
            $workspace->account,
            $user,
            $workspace,
            AiFeature::IdeaGeneration,
            $response->usage,
            $response->meta->model,
        );

        $title = Str::substr(trim((string) data_get($response, 'title')), 0, 255);

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'body' => Str::substr(trim((string) data_get($response, 'body')), 0, AiPromptRules::PROMPT_MAX_LENGTH),
        ];
    }
}
