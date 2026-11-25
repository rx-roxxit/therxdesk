<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A call could not reserve its credits, either because the month's allowance
 * is gone or because a parallel call took the last credit. It carries the
 * same message the useAi policy returns, so the user sees one answer.
 */
class AiCreditsExhausted extends RuntimeException
{
    public function __construct(string $message, private readonly string $denialCode)
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => $this->denialCode,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return back()->with('flash.error', $this->getMessage());
    }
}
