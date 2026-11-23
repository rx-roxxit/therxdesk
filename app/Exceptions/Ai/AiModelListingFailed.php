<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The provider would not list its models. The internal admin API answers 502;
 * nothing was saved.
 */
class AiModelListingFailed extends RuntimeException
{
    public static function missingKey(): self
    {
        return new self('The AI provider key is not configured.');
    }

    public static function because(int $status): self
    {
        return new self("The AI provider answered HTTP {$status} while listing models.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_BAD_GATEWAY);
    }
}
