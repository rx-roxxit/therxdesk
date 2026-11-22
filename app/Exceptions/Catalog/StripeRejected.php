<?php

declare(strict_types=1);

namespace App\Exceptions\Catalog;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stripe refused a Catalog change. The Catalog service throws it before any
 * database write, so nothing was saved; the internal API answers 502.
 */
class StripeRejected extends RuntimeException
{
    public const string MESSAGE = 'Stripe rejected the Catalog change. Nothing was saved.';

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
