<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Ai\Agents\PostWritingAssistant;
use App\Enums\Ai\AiFeature;
use App\Enums\Ai\PostAssistantMode;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use App\Services\Repurpose\CaptionAdapter;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AssistPostContent
{
    public static function execute(
        Workspace $workspace,
        User $user,
        PostAssistantMode $mode,
        string $currentContent,
        ?string $prompt,
        ?string $previousContent,
        ?Platform $platform,
        ?SocialAccount $account = null,
        ?ContentType $contentType = null,
    ): string {
        $platform = $account->platform ?? $platform;
        $limit = transform($platform, fn (Platform $platform): int => ContentType::maxContentLengthFor($account, $platform, $contentType));

        app(AiSettings::class)->apply(AiFeature::WritingAssistant);

        $credits = app(AiCredits::class);
        $reservation = $credits->reserve(
            $workspace->account,
            $user,
            $workspace,
            AiFeature::WritingAssistant,
            $credits->estimate(AiFeature::WritingAssistant, $currentContent.' '.$prompt),
        );

        try {
            $response = (new PostWritingAssistant($mode, $currentContent, $user->locale, $platform, $previousContent, $limit))
                ->prompt($mode->requiresPrompt() ? trim((string) $prompt) : 'Revise the existing caption as instructed.');
        } catch (Throwable $exception) {
            $credits->release($reservation);

            throw $exception;
        }

        $credits->reconcile($reservation, $response->usage, $response->meta->model);

        $text = trim((string) $response);

        if ($text === '') {
            throw ValidationException::withMessages([
                $mode->requiresPrompt() ? 'prompt' : 'current_content' => __('posts.composer.assistant_error'),
            ]);
        }

        return $platform instanceof Platform
            ? app(CaptionAdapter::class)->adapt($workspace, $user, $text, $platform, $limit)
            : $text;
    }
}
