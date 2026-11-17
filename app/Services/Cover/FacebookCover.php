<?php

declare(strict_types=1);

namespace App\Services\Cover;

use App\Exceptions\Cover\CoverRefused;
use App\Models\Media;
use App\Models\Post;
use App\Services\Social\Meta\GraphError;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Sets a published Page video's or Reel's Cover with `POST /{video-id}/thumbnails`
 * (`is_preferred`), which Facebook accepts once `status.video_status` is
 * `ready`. Both content types take it with the Page token the connect already
 * stores (checked on a real Page, November 2026). The publisher is untouched:
 * the job calls this some minutes after the video is up.
 *
 * @see https://developers.facebook.com/docs/graph-api/reference/video/thumbnails/
 */
class FacebookCover extends AbstractNetworkCover
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

        $this->ensureVideoStatus(data_get($response->json(), 'status.video_status'), self::READY, self::FAILED);
    }

    private function upload(string $videoId, string $accessToken, Media $cover): void
    {
        $response = $this->send(fn (): Response => Http::timeout(120)
            ->attach('source', $this->coverBytes($cover), (string) ($cover->original_filename ?? 'cover'))
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

    protected function networkName(): string
    {
        return 'Facebook';
    }

    /**
     * A rate limit, a transient code or a 5xx; a dead token is final.
     */
    protected function isBusy(Response $response): bool
    {
        return GraphError::isTransientFailure($response);
    }

    protected function failureDetail(Response $response): string
    {
        $code = data_get($response->json(), 'error.code', 'unknown');

        return "code {$code}";
    }
}
