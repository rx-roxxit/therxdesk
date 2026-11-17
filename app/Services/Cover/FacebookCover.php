<?php

declare(strict_types=1);

namespace App\Services\Cover;

use App\Exceptions\Cover\CoverNotReady;
use App\Exceptions\Cover\CoverRefused;
use App\Models\Media;
use App\Models\Post;
use App\Services\Social\Meta\GraphError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Sets a published Page video's or Reel's Cover with `POST /{video-id}/thumbnails`
 * (`is_preferred`), which Facebook accepts once `status.video_status` is
 * `ready`. Both content types take it with the Page token the connect already
 * stores (checked on a real Page, November 2026). The publisher is untouched:
 * the job calls this some minutes after the video is up.
 *
 * @see https://developers.facebook.com/docs/graph-api/reference/video/thumbnails/
 */
class FacebookCover implements NetworkCover
{
    private const string READY = 'ready';

    /**
     * Statuses after which the video never becomes ready.
     *
     * @var list<string>
     */
    private const array FAILED = ['error', 'expired'];

    public function set(Post $post, Media $cover): void
    {
        $videoId = $post->platform_post_id;
        $accessToken = $post->socialAccount?->access_token;

        if (blank($videoId) || blank($accessToken)) {
            throw new CoverRefused('The video is no longer on Facebook.');
        }

        $this->ensureReady($videoId, $accessToken);
        $this->upload($videoId, $accessToken, $cover);
    }

    private function ensureReady(string $videoId, string $accessToken): void
    {
        $response = $this->send(fn (): Response => Http::timeout(60)->get($this->url($videoId), [
            'fields' => 'status',
            'access_token' => $accessToken,
        ]));

        $this->throwForStatus($response);

        $status = data_get($response->json(), 'status.video_status');

        if ($status === self::READY) {
            return;
        }

        if (in_array($status, self::FAILED, true)) {
            throw new CoverRefused("Facebook did not process the video ({$status}).");
        }

        throw new CoverNotReady('Facebook is still processing the video.');
    }

    private function upload(string $videoId, string $accessToken, Media $cover): void
    {
        $bytes = Storage::get($cover->path);

        if (blank($bytes)) {
            throw new CoverRefused('The Cover image is no longer on the server.');
        }

        $response = $this->send(fn (): Response => Http::timeout(120)
            ->attach('source', $bytes, (string) ($cover->original_filename ?? 'cover'))
            ->post($this->url($videoId).'/thumbnails', [
                'is_preferred' => 'true',
                'access_token' => $accessToken,
            ]));

        $this->throwForStatus($response);

        if (data_get($response->json(), 'success') !== true) {
            throw new CoverRefused('Facebook did not accept the Cover image.');
        }
    }

    private function url(string $videoId): string
    {
        return config('trypost.platforms.facebook.graph_api')."/{$videoId}";
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $exception) {
            throw new CoverNotReady('Facebook could not be reached.', previous: $exception);
        }
    }

    /**
     * A busy Facebook (a rate limit, a transient code, 5xx) is tried again; any
     * other refusal, a dead token included, is final.
     */
    private function throwForStatus(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $code = data_get($response->json(), 'error.code', 'unknown');
        $message = "Facebook answered {$response->status()} (code {$code}).";

        if (GraphError::isTransientFailure($response)) {
            throw new CoverNotReady($message);
        }

        throw new CoverRefused($message);
    }
}
