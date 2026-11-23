<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Ai\AssistPostContent;
use App\Enums\Ai\AiFeature;
use App\Enums\Ai\PostAssistantMode;
use App\Enums\PostHog\AiEvent;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Http\Requests\App\Ai\AssistPostContentRequest;
use App\Jobs\PostHog\CaptureAccountEvent;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiProviderFailure;
use App\Services\PostHogService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Exceptions\AiException;
use Symfony\Component\HttpFoundation\Response;

class PostAiAssistantController extends Controller
{
    public function __invoke(AssistPostContentRequest $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $gate = Gate::inspect('useAi', $workspace->account);
        if ($gate->denied()) {
            return response()->json(['message' => $gate->message(), 'code' => $gate->code()], Response::HTTP_PAYMENT_REQUIRED);
        }

        $mode = PostAssistantMode::from($request->string('mode')->toString());

        try {
            $content = AssistPostContent::execute(
                workspace: $workspace,
                user: $request->user(),
                mode: $mode,
                currentContent: $request->string('current_content')->toString(),
                prompt: $request->input('prompt'),
                previousContent: $request->input('previous_content'),
                platform: $request->enum('platform', Platform::class),
                contentType: $request->enum('content_type', ContentType::class),
                account: $request->filled('social_account_id')
                    ? $workspace->socialAccounts()->find($request->input('social_account_id'))
                    : null,
            );
        } catch (AiException|RequestException|ConnectionException $exception) {
            app(AiProviderFailure::class)->report($exception, AiFeature::WritingAssistant);

            return response()->json(['message' => __('rxdesk.ai.unavailable')], Response::HTTP_BAD_GATEWAY);
        }

        rescue(function () use ($request, $workspace, $mode): void {
            CaptureAccountEvent::dispatchIf(
                PostHogService::isEnabled(),
                (string) $request->user()->id,
                (string) $workspace->account_id,
                AiEvent::AssistUsed,
                [
                    'mode' => $mode->value,
                    'workspace_id' => (string) $workspace->id,
                ],
            );
        });

        return response()->json([
            'content' => $content,
            'ai_credits' => app(AiCredits::class)->payload($workspace->account),
        ]);
    }
}
