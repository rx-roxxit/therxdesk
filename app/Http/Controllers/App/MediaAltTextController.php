<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Ai\GenerateMediaAltText;
use App\Actions\Media\ResolveWorkspaceMedia;
use App\Enums\Ai\AiFeature;
use App\Http\Requests\App\Ai\GenerateMediaAltTextRequest;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiProviderFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Exceptions\AiException;
use Symfony\Component\HttpFoundation\Response;

class MediaAltTextController extends Controller
{
    public function __invoke(GenerateMediaAltTextRequest $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $asset = ResolveWorkspaceMedia::execute($workspace, [$request->string('media_id')->toString()])->first();
        abort_if($asset === null, Response::HTTP_NOT_FOUND);
        abort_unless($asset->isImage(), Response::HTTP_UNPROCESSABLE_ENTITY, __('posts.composer.media_editor.alt_generate_image_only'));

        $gate = Gate::inspect('useAi', $workspace->account);
        if ($gate->denied()) {
            return response()->json(['message' => $gate->message(), 'code' => $gate->code()], Response::HTTP_PAYMENT_REQUIRED);
        }

        try {
            $altText = GenerateMediaAltText::execute($request->user(), $asset, $workspace);
        } catch (AiException|RequestException|ConnectionException $exception) {
            app(AiProviderFailure::class)->report($exception, AiFeature::AltText);

            return response()->json(['message' => __('rxdesk.ai.unavailable')], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'alt_text' => $altText,
            'ai_credits' => app(AiCredits::class)->payload($workspace->account),
        ]);
    }
}
