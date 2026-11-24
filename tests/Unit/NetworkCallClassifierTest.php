<?php

declare(strict_types=1);

use App\Services\NetworkLimits\NetworkCallClassifier;
use GuzzleHttp\Psr7\Uri;

test('a platform api url maps to its network', function (string $configKey, string $path, string $network) {
    $url = (string) config($configKey).$path;

    expect((new NetworkCallClassifier)->networkFor(new Uri($url)))->toBe($network);
})->with([
    'tiktok' => ['trypost.platforms.tiktok.api', '/user/info/', 'tiktok'],
    'pinterest' => ['trypost.platforms.pinterest.api', '/pins', 'pinterest'],
    'youtube data' => ['trypost.platforms.youtube.data_api', '/channels?part=snippet', 'youtube'],
    'youtube upload' => ['trypost.platforms.youtube.upload_api', '/videos?uploadType=resumable', 'youtube'],
    'youtube analytics' => ['trypost.platforms.youtube.analytics_api', '/reports/query', 'youtube'],
]);

test('a host or path outside the platform apis is not counted', function (string $url) {
    expect((new NetworkCallClassifier)->networkFor(new Uri($url)))->toBeNull();
})->with([
    'google drive' => ['https://www.googleapis.com/drive/v3/files'],
    'graph api' => ['https://graph.facebook.com/v25.0/me'],
    'youtube cdn' => ['https://i.ytimg.com/vi/abc/hqdefault.jpg'],
    'another host' => ['https://example.com/v2/user/info/'],
]);

test('a path that only starts with the base path is not counted', function () {
    $url = config('trypost.platforms.youtube.data_api').'0/channels';

    expect((new NetworkCallClassifier)->networkFor(new Uri($url)))->toBeNull();
});
