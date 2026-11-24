<?php

declare(strict_types=1);

namespace App\Services\NetworkLimits;

use Illuminate\Support\Uri;
use Psr\Http\Message\UriInterface;

/**
 * Which network a request belongs to, from its host and path alone. OAuth and
 * token hosts, media CDNs and every other service are deliberately absent, so
 * they are never counted.
 */
class NetworkCallClassifier
{
    public function networkFor(UriInterface $uri): ?string
    {
        $request = Uri::of($uri);
        $host = strtolower((string) $request->host());

        if ($host === '') {
            return null;
        }

        $segments = $request->pathSegments()->all();

        if (in_array('oauth', $segments, true)) {
            return null;
        }

        foreach ($this->targets() as $network => $urls) {
            foreach ($urls as $url) {
                $base = Uri::of($url);

                if (strtolower((string) $base->host()) !== $host) {
                    continue;
                }

                if ($this->startsWithSegments($segments, $base->pathSegments()->all())) {
                    return $network;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function targets(): array
    {
        return [
            'youtube' => [
                (string) config('trypost.platforms.youtube.data_api'),
                (string) config('trypost.platforms.youtube.upload_api'),
                (string) config('trypost.platforms.youtube.analytics_api'),
            ],
            'tiktok' => [
                (string) config('trypost.platforms.tiktok.api'),
            ],
            'pinterest' => [
                (string) config('trypost.platforms.pinterest.api'),
            ],
        ];
    }

    /**
     * @param  list<string>  $segments
     * @param  list<string>  $prefix
     */
    private function startsWithSegments(array $segments, array $prefix): bool
    {
        if ($prefix === []) {
            return true;
        }

        return array_slice($segments, 0, count($prefix)) === $prefix;
    }
}
