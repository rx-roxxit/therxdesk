<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Idea\GenerateIdea;
use App\Enums\Ai\AiFeature;
use App\Http\Requests\App\Idea\GenerateIdeaRequest;
use App\Http\Resources\App\GeneratedIdeaResource;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiProviderFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Exceptions\AiException;
use Symfony\Component\HttpFoundation\Response;

class IdeaGenerateController extends Controller
{
    public function __invoke(GenerateIdeaRequest $request): GeneratedIdeaResource|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $gate = Gate::inspect('useAi', $workspace->account);
        if ($gate->denied()) {
            return response()->json(['message' => $gate->message(), 'code' => $gate->code()], Response::HTTP_PAYMENT_REQUIRED);
        }

        try {
            $idea = GenerateIdea::execute(
                user: $request->user(),
                business: $request->validated('business'),
                audience: $request->validated('audience'),
                notes: $request->validated('notes'),
                workspace: $workspace,
            );
        } catch (AiException|RequestException|ConnectionException $exception) {
            app(AiProviderFailure::class)->report($exception, AiFeature::IdeaGeneration);

            return response()->json(['message' => __('rxdesk.ai.unavailable')], Response::HTTP_BAD_GATEWAY);
        }

        if ($idea === null) {
            return response()->json(['message' => __('create.ideas.errors.generate_failed')], Response::HTTP_BAD_GATEWAY);
        }

        return new GeneratedIdeaResource([
            ...$idea,
            'ai_credits' => app(AiCredits::class)->payload($workspace->account),
        ]);
    }
}
