<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\NetworkLimitAccountResource;
use App\Models\AccountNetworkCall;
use App\Models\NetworkLimitSetting;
use App\Services\NetworkLimits\NetworkLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The System admin's view of the app's own daily network call budgets: each
 * bucket with its capacity and status, and the Accounts behind one bucket.
 */
class NetworkLimitController
{
    public function __construct(private readonly NetworkLimits $limits) {}

    public function show(): JsonResponse
    {
        $setting = NetworkLimitSetting::current();

        return response()->json([
            'date' => $this->limits->today()->toDateString(),
            'reset_at' => $this->limits->resetAt()->toIso8601String(),
            'thresholds' => [
                'today_percent' => $setting->today_percent,
                'urgent_percent' => $setting->urgent_percent,
                'account_digest_percent' => $setting->account_digest_percent,
            ],
            'networks' => array_map(
                fn (string $network): array => $this->networkRow($network, $setting),
                $this->limits->networks(),
            ),
        ]);
    }

    public function accounts(string $network): AnonymousResourceCollection
    {
        if (! in_array($network, $this->limits->networks(), true)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $capacity = $this->limits->capacity($network) ?? 0;

        $rows = $this->limits->accountRows($network)
            ->paginate((int) config('app.pagination.default'));

        $rows->through(function (AccountNetworkCall $row) use ($capacity): AccountNetworkCall {
            $row->setAttribute('percent', round($this->limits->percent((int) $row->calls, $capacity), 1));

            return $row;
        });

        return NetworkLimitAccountResource::collection($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function networkRow(string $network, NetworkLimitSetting $setting): array
    {
        $bucket = $this->limits->bucket($network);
        $percent = round($bucket['percent'], 1);

        return [
            ...$bucket,
            'percent' => $percent,
            'top_accounts' => $percent >= $setting->today_percent
                ? $this->limits->topAccounts($network, 5)
                : [],
        ];
    }
}
