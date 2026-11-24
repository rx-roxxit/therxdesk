<?php

declare(strict_types=1);

use App\Models\AccountNetworkCall;
use App\Models\NetworkCallBucket;
use App\Models\User;
use App\Services\NetworkLimits\NetworkCallContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    config([
        'trypost.self_hosted' => false,
        'rxdesk.network_limits.enabled' => true,
        'rxdesk.network_limits.buckets.tiktok' => 100,
        'rxdesk.network_limits.buckets.pinterest' => 100,
        'rxdesk.network_limits.buckets.youtube' => 100,
    ]);
});

test('a platform api call increments its network bucket', function (string $configKey, string $path) {
    Http::fake();
    $url = (string) config($configKey).$path;

    Http::get($url);

    expect(NetworkCallBucket::query()->value('calls'))->toBe(1);
})->with([
    'tiktok' => ['trypost.platforms.tiktok.api', '/user/info/'],
    'pinterest' => ['trypost.platforms.pinterest.api', '/pins'],
    'youtube' => ['trypost.platforms.youtube.data_api', '/channels'],
]);

test('a non-platform host is not counted', function () {
    Http::fake();

    Http::get('https://example.com/v2/pins');

    expect(NetworkCallBucket::query()->count())->toBe(0);
});

test('a self-hosted install counts nothing', function () {
    config(['trypost.self_hosted' => true]);
    Http::fake();

    Http::get(config('trypost.platforms.tiktok.api').'/user/info/');

    expect(NetworkCallBucket::query()->count())->toBe(0);
});

test('a disabled feature counts nothing', function () {
    config(['rxdesk.network_limits.enabled' => false]);
    Http::fake();

    Http::get(config('trypost.platforms.tiktok.api').'/user/info/');

    expect(NetworkCallBucket::query()->count())->toBe(0);
});

test('a network without a positive capacity counts nothing', function () {
    config(['rxdesk.network_limits.buckets.tiktok' => 0]);
    Http::fake();

    Http::get(config('trypost.platforms.tiktok.api').'/user/info/');

    expect(NetworkCallBucket::query()->count())->toBe(0);
});

test('inside the Account context the Account row increments too', function () {
    Http::fake();
    $accountId = (string) User::factory()->create()->account_id;

    app(NetworkCallContext::class)->run(
        $accountId,
        fn () => Http::get(config('trypost.platforms.tiktok.api').'/user/info/'),
    );

    expect(NetworkCallBucket::query()->value('calls'))->toBe(1)
        ->and(AccountNetworkCall::query()->where('account_id', $accountId)->value('calls'))->toBe(1)
        ->and((string) AccountNetworkCall::query()->value('date'))
        ->toStartWith(CarbonImmutable::now('UTC')->toDateString());
});

test('outside the Account context only the bucket increments', function () {
    Http::fake();
    User::factory()->create();

    Http::get(config('trypost.platforms.tiktok.api').'/user/info/');

    expect(NetworkCallBucket::query()->value('calls'))->toBe(1)
        ->and(AccountNetworkCall::query()->count())->toBe(0);
});
