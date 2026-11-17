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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Sets a published Short's Cover with `thumbnails.set`, which YouTube honours
 * only once `processingDetails.processingStatus` is `succeeded` (Shorts
 * included). The publisher is untouched: the job calls this some minutes
 * after the video is up.
 *
 * @see https://developers.google.com/youtube/v3/docs/thumbnails/set
 */
class YouTubeCover implements NetworkCover
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

        try {
            app(ConnectionVerifier::class)->refreshToken($account);
        } catch (PlatformUnavailableException $exception) {
            throw new CoverNotReady($exception->getMessage(), previous: $exception);
        } catch (TokenExpiredException $exception) {
            throw new CoverRefused($exception->getMessage(), previous: $exception);
        }
    }

    private function ensureProcessed(SocialAccount $account, string $videoId): void
    {
        $response = $this->send(fn (): Response => $this->client($account)->get(config('trypost.platforms.youtube.data_api').'/videos', [
            'part' => 'processingDetails',
            'id' => $videoId,
        ]));

        $this->throwForStatus($response);

        $status = data_get($response->json(), 'items.0.processingDetails.processingStatus');

        if ($status === self::PROCESSING_SUCCEEDED) {
            return;
        }

        if (in_array($status, self::PROCESSING_FAILED, true)) {
            throw new CoverRefused("YouTube did not process the video ({$status}).");
        }

        throw new CoverNotReady('YouTube is still processing the video.');
    }

    private function upload(SocialAccount $account, string $videoId, Media $cover): void
    {
        $bytes = Storage::get($cover->path);

        if (blank($bytes)) {
            throw new CoverRefused('The Cover image is no longer on the server.');
        }

        $response = $this->send(fn (): Response => $this->client($account)
            ->withBody($bytes, (string) $cover->mime_type)
            ->post(config('trypost.platforms.youtube.upload_api').'/thumbnails/set?'.http_build_query([
                'videoId' => $videoId,
                'uploadType' => 'media',
            ])));

        $this->throwForStatus($response);
    }

    private function client(SocialAccount $account): PendingRequest
    {
        return Http::withToken((string) $account->access_token)->timeout(60);
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $exception) {
            throw new CoverNotReady($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * A busy YouTube (429, 5xx) is tried again; any other refusal is final.
     */
    private function throwForStatus(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = data_get($response->json(), 'error.errors.0.reason', 'error');
        $message = "YouTube answered {$response->status()} ({$reason}).";

        if ($response->status() === 429 || $response->serverError()) {
            throw new CoverNotReady($message);
        }

        throw new CoverRefused($message);
    }
}
