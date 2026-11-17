<?php

declare(strict_types=1);

namespace App\Services\Cover;

use App\Exceptions\Cover\CoverNotReady;
use App\Exceptions\Cover\CoverRefused;
use App\Models\Media;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Storage;

/**
 * What every network's Cover setter shares: sending a request (an unreachable
 * network is not ready yet), reading how far the network got with the video,
 * reading the Cover image's bytes, and telling a busy network from a refusal.
 */
abstract class AbstractNetworkCover implements NetworkCover
{
    /**
     * The network's name as a message says it.
     */
    abstract protected function networkName(): string;

    /**
     * Whether a failed response is the network being busy, so the job tries again.
     */
    abstract protected function isBusy(Response $response): bool;

    /**
     * What the network said went wrong, for the recorded message.
     */
    abstract protected function failureDetail(Response $response): string;

    /**
     * @param  callable(): Response  $request
     */
    protected function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $exception) {
            throw new CoverNotReady("{$this->networkName()} could not be reached.", previous: $exception);
        }
    }

    /**
     * A busy network is tried again; any other refusal is final.
     */
    protected function throwForStatus(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = "{$this->networkName()} answered {$response->status()} ({$this->failureDetail($response)}).";

        if ($this->isBusy($response)) {
            throw new CoverNotReady($message);
        }

        throw new CoverRefused($message);
    }

    /**
     * Where the network got with the video: ready to take the Cover, failed
     * for good, or still processing.
     *
     * @param  list<string>  $failedStatuses
     */
    protected function ensureVideoStatus(mixed $status, string $readyStatus, array $failedStatuses): void
    {
        if ($status === $readyStatus) {
            return;
        }

        if (in_array($status, $failedStatuses, true)) {
            throw new CoverRefused("{$this->networkName()} did not process the video ({$status}).");
        }

        throw new CoverNotReady("{$this->networkName()} is still processing the video.");
    }

    protected function coverBytes(Media $cover): string
    {
        $bytes = Storage::get($cover->path);

        if (blank($bytes)) {
            throw new CoverRefused('The Cover image is no longer on the server.');
        }

        return $bytes;
    }
}
