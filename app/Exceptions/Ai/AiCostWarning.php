<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A change would push an active Plan past 10% of its discounted monthly price.
 * The admin has to send `confirm: true`; until then nothing is saved and the
 * response carries the after-table.
 */
class AiCostWarning extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $cost
     */
    public function __construct(private readonly array $cost)
    {
        parent::__construct('This change puts an active Plan over 10% of its discounted monthly price. Send confirm: true to save.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'requires_confirmation' => true,
            'cost' => $this->cost,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
