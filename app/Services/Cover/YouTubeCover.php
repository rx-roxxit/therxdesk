<?php

declare(strict_types=1);

namespace App\Services\Cover;

use App\Exceptions\Cover\CoverNotReady;
use App\Exceptions\Cover\CoverRefused;
use App\Exceptions\PlatformUnavailableException;
use App\Exceptions\TokenExpiredException;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Social\ConnectionVerifier;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Sets a published Short's Cover with `thumbnails.set`, which YouTube honours
 * only once `processingDetails.processingStatus` is `succeeded` (Shorts
 * included). The publisher is untouched: the job calls this some minutes
 * after the video is up.
 *
 * @see https://developers.google.com/youtube/v3/docs/thumbnails/set
 */
class YouTubeCover extends AbstractNetworkCover
{
    private const string PROCESSING_SUCCEEDED = 'succeeded';

    /**
     * Statuses after which the video never becomes ready.
     *
     * @var list<string>
     */
    private const array PROCESSING_FAILED = ['failed', 'terminated'];

    public function set(Post $post, Media $cover): void
    {
        $videoId = $post->platform_post_id;
        $account = $post->socialAccount;

        if (blank($videoId) || blank($account)) {
            throw new CoverRefused('The video is no longer on YouTube.');
        }

        $this->ensureToken($account);
        $this->ensureProcessed($account, $videoId);
        $this->upload($account, $videoId, $cover);
    }

    private function ensureToken(SocialAccount $account): void
    {
        if (! $account->needsProactiveTokenRefresh()) {
            return;
        }

        $this->refreshToken($account);
    }

    /**
     * Refreshes through the connection code the publisher uses. A refresh
     * Google could not answer is tried again later; one it rejected (the grant
     * is revoked, `invalid_grant`) is final.
     */
    private function refreshToken(SocialAccount $account): void
    {
        try {
            app(ConnectionVerifier::class)->refreshToken($account);
        } catch (PlatformUnavailableException $exception) {
            throw new CoverNotReady($exception->getMessage(), previous: $exception);
        } catch (TokenExpiredException $exception) {
            throw new CoverRefused($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * Sends an authorized request. A 401 means the access token expired before
     * its stored time: it is refreshed once and the request sent again; a
     * second 401 is not a refusal of the Cover, so the job looks again later.
     *
     * @param  callable(): Response  $request
     */
    private function sendAuthorized(SocialAccount $account, callable $request): Response
    {
        $response = $this->send($request);

        if ($response->status() !== 401) {
            return $response;
        }

        $this->refreshToken($account);

        $response = $this->send($request);

        if ($response->status() === 401) {
            throw new CoverNotReady('YouTube still refused the access token after a refresh.');
        }

        return $response;
    }

    private function ensureProcessed(SocialAccount $account, string $videoId): void
    {
        $response = $this->sendAuthorized($account, fn (): Response => $this->client($account)->get(config('trypost.platforms.youtube.data_api').'/videos', [
            'part' => 'processingDetails',
            'id' => $videoId,
        ]));

        $this->throwForStatus($response);

        $this->ensureVideoStatus(data_get($response->json(), 'items.0.processingDetails.processingStatus'), self::PROCESSING_SUCCEEDED, self::PROCESSING_FAILED);
    }

    private function upload(SocialAccount $account, string $videoId, Media $cover): void
    {
        $response = $this->sendAuthorized($account, fn (): Response => $this->client($account)
            ->withBody($this->coverBytes($cover), (string) $cover->mime_type)
            ->withQueryParameters(['videoId' => $videoId, 'uploadType' => 'media'])
            ->post(config('trypost.platforms.youtube.upload_api').'/thumbnails/set'));

        $this->throwForStatus($response);
    }

    private function client(SocialAccount $account): PendingRequest
    {
        return Http::withToken((string) $account->access_token)->timeout(60);
    }

    protected function networkName(): string
    {
        return 'YouTube';
    }

    protected function isBusy(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    protected function failureDetail(Response $response): string
    {
        return (string) data_get($response->json(), 'error.errors.0.reason', 'error');
    }
}
