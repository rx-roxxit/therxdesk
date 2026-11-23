<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * A model failed its one test call. The internal admin API answers 422 with
 * the model's error; an admin may see it, a customer never does.
 */
class ModelProbeFailed extends RuntimeException
{
    public static function because(Throwable $exception): self
    {
        return new self('The model did not answer. Nothing was saved.', 0, $exception);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->getPrevious()?->getMessage(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
