<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Support\AnswerSupportQuestion;
use App\Enums\Ai\AiFeature;
use App\Enums\Support\SupportMessageRole;
use App\Http\Requests\App\Support\FeedbackSupportMessageRequest;
use App\Http\Requests\App\Support\StoreSupportMessageRequest;
use App\Http\Resources\App\Support\SupportMessageResource;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Ai\AiProviderFailure;
use App\Support\SupportChat\SupportChatLimits;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Exceptions\AiException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits are counted before the message is stored, so a refused message costs
 * nothing.
 */
class SupportMessageController extends Controller
{
    public function __construct(private readonly SupportChatLimits $limits) {}

    public function store(StoreSupportMessageRequest $request, SupportConversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->user_id === $user->id, Response::HTTP_NOT_FOUND);

        $refusal = $this->limits->refusal($conversation, $user);

        if ($refusal !== null) {
            return $this->tooMany($refusal['code'], $conversation, $request);
        }

        $retryAfter = $this->limits->claimMinuteSlot($user);

        if ($retryAfter !== null) {
            return $this->tooMany('minute', $conversation, $request, $retryAfter);
        }

        $conversation->forceFill([
            'last_page' => $request->filled('page') ? $request->input('page') : $conversation->last_page,
            'last_message_at' => now(),
        ])->save();

        $question = $conversation->messages()->create([
            'role' => SupportMessageRole::User,
            'content' => $request->string('message')->toString(),
        ]);

        return $this->answer($conversation, $question, $request);
    }

    public function retry(Request $request, SupportMessage $message): JsonResponse
    {
        $user = $request->user();
        $conversation = $message->conversation;

        abort_unless($conversation->user_id === $user->id, Response::HTTP_NOT_FOUND);

        $isLast = SupportMessage::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first()
            ?->is($message) ?? false;

        if ($message->isAnswer() || ! $isLast) {
            return response()->json(['code' => 'not_retryable'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $retryAfter = $this->limits->claimMinuteSlot($user);

        if ($retryAfter !== null) {
            return $this->tooMany('minute', $conversation, $request, $retryAfter);
        }

        return $this->answer($conversation, $message, $request);
    }

    public function feedback(FeedbackSupportMessageRequest $request, SupportMessage $message): JsonResponse
    {
        abort_unless($message->conversation->user_id === $request->user()->id, Response::HTTP_NOT_FOUND);

        if (! $message->isAnswer()) {
            return response()->json(['code' => 'not_an_answer'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message->update([
            'feedback' => $request->input('feedback'),
        ]);

        return response()->json(['message' => SupportMessageResource::make($message->refresh())]);
    }

    private function answer(SupportConversation $conversation, SupportMessage $question, Request $request): JsonResponse
    {
        try {
            $text = AnswerSupportQuestion::execute($conversation, $question);
        } catch (AiException|RequestException|ConnectionException|RuntimeException $exception) {
            app(AiProviderFailure::class)->report($exception, AiFeature::SupportChat);

            return response()->json([
                'code' => 'unavailable',
                'message_id' => $question->id,
                'limits' => $this->limits->state($conversation, $request->user()),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $answer = $conversation->messages()->create([
            'role' => SupportMessageRole::Assistant,
            'content' => $text,
        ]);

        return response()->json([
            'messages' => [
                SupportMessageResource::make($question->refresh()),
                SupportMessageResource::make($answer),
            ],
            'limits' => $this->limits->state($conversation, $request->user()),
        ]);
    }

    private function tooMany(string $code, SupportConversation $conversation, Request $request, ?int $retryAfter = null): JsonResponse
    {
        $response = response()->json([
            'code' => $code,
            'retry_after' => $retryAfter,
            'limits' => $this->limits->state($conversation, $request->user()),
        ], Response::HTTP_TOO_MANY_REQUESTS);

        return $retryAfter === null
            ? $response
            : $response->withHeaders(['Retry-After' => (string) $retryAfter]);
    }
}
