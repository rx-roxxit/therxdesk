<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Audit\RecordAuditEntry;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\UpdateNetworkLimitThresholdsRequest;
use App\Models\NetworkLimitSetting;
use Illuminate\Http\JsonResponse;

/**
 * The System admin's network bucket thresholds. Every write is audited with
 * the values before and after it.
 */
class NetworkLimitThresholdController
{
    public function update(UpdateNetworkLimitThresholdsRequest $request): JsonResponse
    {
        $setting = NetworkLimitSetting::current();
        $before = $this->snapshot($setting);

        $setting->fill($request->validated());
        $setting->save();

        $after = $this->snapshot($setting);

        RecordAuditEntry::execute(
            AuthenticateInternalApi::actor($request),
            'network-limit-thresholds.update',
            $setting,
            $before,
            $after,
        );

        return response()->json($after);
    }

    /**
     * @return array{today_percent: int, urgent_percent: int, account_digest_percent: int}
     */
    private function snapshot(NetworkLimitSetting $setting): array
    {
        return [
            'today_percent' => $setting->today_percent,
            'urgent_percent' => $setting->urgent_percent,
            'account_digest_percent' => $setting->account_digest_percent,
        ];
    }
}
