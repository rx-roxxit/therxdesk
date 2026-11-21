<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\Ops\HealthReport;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthReport $report): JsonResponse
    {
        return response()->json($report->toArray());
    }
}
