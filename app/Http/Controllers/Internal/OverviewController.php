<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Ops\OverviewReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * The System admin's Overview figures: publishing over 14 days, usage totals
 * and the largest Accounts. Read-only, and no customer content leaves it.
 */
class OverviewController extends Controller
{
    public function __construct(private readonly OverviewReport $report) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->report->build(CarbonImmutable::now('UTC')));
    }
}
