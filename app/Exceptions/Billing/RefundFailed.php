<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stripe refused a Refund and cancel. The internal API answers 502 with a
 * generic message; the Stripe error is kept as the previous exception for the
 * log, never returned to the caller.
 */
class RefundFailed extends RuntimeException
{
    public const string MESSAGE = 'Stripe refused the refund and cancel.';

    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }

    public static function because(ApiErrorException $exception): self
    {
        return new self($exception);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => self::MESSAGE], Response::HTTP_BAD_GATEWAY);
    }
}
