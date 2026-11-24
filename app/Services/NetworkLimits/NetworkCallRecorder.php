<?php

declare(strict_types=1);

namespace App\Services\NetworkLimits;

use App\Models\AccountNetworkCall;
use App\Models\NetworkCallBucket;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Counts a request at the moment it goes out, before it is sent. A failure
 * here must never break the outgoing call: nothing is counted when the
 * network, the capacity or the feature is absent, and anything else is only
 * logged.
 */
class NetworkCallRecorder
{
    public function __construct(
        private readonly NetworkCallClassifier $classifier,
        private readonly NetworkCallContext $context,
        private readonly NetworkLimits $limits,
        private readonly NetworkBucketMonitor $monitor,
    ) {}

    public function record(RequestInterface $request): void
    {
        try {
            if ((bool) config('trypost.self_hosted')) {
                return;
            }

            if (! (bool) config('rxdesk.network_limits.enabled')) {
                return;
            }

            $network = $this->classifier->networkFor($request->getUri());

            if ($network === null) {
                return;
            }

            if ($this->limits->capacity($network) === null) {
                return;
            }

            $today = $this->limits->today();

            NetworkCallBucket::incrementFor($network, $today);

            $accountId = $this->context->currentAccountId();

            if ($accountId !== null) {
                AccountNetworkCall::incrementFor($accountId, $network, $today);
            }

            $this->monitor->evaluate($network);
        } catch (Throwable $e) {
            Log::debug('Network call not counted.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
