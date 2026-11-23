<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\Support\SupportChatCounter;
use App\Http\Requests\App\Support\StoreSupportHandoffRequest;
use App\Mail\SupportHandoff;
use App\Models\SupportConversation;
use App\Support\SupportChat\SupportChatCounters;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * "Contact the team": the email is sent before anything is marked, so a
 * failure never claims the conversation reached a person.
 */
class SupportHandoffController extends Controller
{
    public function store(StoreSupportHandoffRequest $request, SupportConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, Response::HTTP_NOT_FOUND);

        if ($request->filled('page')) {
            $conversation->forceFill(['last_page' => $request->input('page')])->save();
        }

        try {
            Mail::to((string) config('rxdesk.support.email'))->send(new SupportHandoff(
                $conversation,
                $request->string('email')->toString(),
                $request->string('message')->toString(),
            ));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['code' => 'send_failed'], Response::HTTP_BAD_GATEWAY);
        }

        $alreadyEscalated = filled($conversation->escalated_at);

        $conversation->forceFill(['escalated_at' => now()])->save();

        if (! $alreadyEscalated) {
            app(SupportChatCounters::class)->increment(SupportChatCounter::Handoffs);
        }

        return response()->json([
            'escalated_at' => $conversation->escalated_at?->toIso8601String(),
        ]);
    }
}
