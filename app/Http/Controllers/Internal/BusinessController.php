<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Support\Business\BusinessReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * The System admin's Business figures, read-only and counted from our own
 * database: current MRR, the waterfall, churn, Trial conversion, Offers and
 * the Support chat volume. No customer content leaves this endpoint.
 */
class BusinessController
{
    public function __construct(private readonly BusinessReport $report) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->report->build(CarbonImmutable::now('UTC')));
    }
}
