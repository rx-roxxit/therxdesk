<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Ai\AskTheDesk;
use App\Enums\Ai\AiFeature;
use App\Enums\AskTheDesk\AskTheDeskRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\AskTheDeskRequest;
use App\Models\AskTheDeskMessage;
use App\Services\Ai\AiProviderFailure;
use App\Support\AskTheDesk\AskTheDeskLimits;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Laravel\Ai\Exceptions\AiException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The System admin's Ask the Desk. It answers from the page aggregates the
 * admin app sends, and its 30-day transcript names no customer.
 */
class AskTheDeskController extends Controller
{
    public function __construct(private readonly AskTheDeskLimits $limits) {}

    public function store(AskTheDeskRequest $request): JsonResponse
    {
        abort_if(config('trypost.self_hosted'), Response::HTTP_NOT_FOUND);

        $used = $this->limits->usedToday();

        if ($used >= $this->limits->max()) {
            return $this->tooMany($used);
        }

        $question = $request->string('question')->trim()->toString();

        AskTheDeskMessage::query()->create([
            'role' => AskTheDeskRole::User,
            'content' => $question,
        ]);

        try {
            $answer = AskTheDesk::execute($request->array('page_aggregates'), $question);
        } catch (AiException|RequestException|ConnectionException|RuntimeException $exception) {
            app(AiProviderFailure::class)->report($exception, AiFeature::AskTheDesk);

            return response()->json([
                'code' => 'unavailable',
                'message' => __('rxdesk.ai.unavailable'),
                'limits' => $this->limits->state(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        AskTheDeskMessage::query()->create([
            'role' => AskTheDeskRole::Assistant,
            'content' => $answer,
        ]);

        return response()->json([
            'answer' => $answer,
            'limits' => $this->limits->state(),
        ]);
    }

    private function tooMany(int $used): JsonResponse
    {
        $max = $this->limits->max();
        $retryAfter = $this->limits->secondsUntilReset();

        return response()->json([
            'code' => 'daily_limit',
            'message' => __('rxdesk.ask_the_desk.daily_limit', ['max' => $max]),
            'retry_after' => $retryAfter,
            'limits' => $this->limits->state($used),
        ], Response::HTTP_TOO_MANY_REQUESTS)->withHeaders(['Retry-After' => (string) $retryAfter]);
    }
}
