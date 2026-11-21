<?php

declare(strict_types=1);

use App\Jobs\PingHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

test('it pings the configured heartbeat url from the queue', function () {
    config(['rxdesk.heartbeat.url' => 'https://heartbeat.test/hb']);
    Http::fake(['https://heartbeat.test/*' => Http::response('', 200)]);

    (new PingHeartbeat)->handle();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://heartbeat.test/hb');
});

test('a blank url does nothing', function () {
    config(['rxdesk.heartbeat.url' => null]);
    Http::fake();

    (new PingHeartbeat)->handle();

    Http::assertNothingSent();
});

test('a non-success answer throws so the job retries', function () {
    config(['rxdesk.heartbeat.url' => 'https://heartbeat.test/hb']);
    Http::fake(['https://heartbeat.test/*' => Http::response('', 500)]);

    expect(fn () => (new PingHeartbeat)->handle())->toThrow(RequestException::class);
});

test('it is a queued job', function () {
    expect(new PingHeartbeat)->toBeInstanceOf(ShouldQueue::class);
});
