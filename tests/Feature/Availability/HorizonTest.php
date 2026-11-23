<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Support\Availability\Availability;

test('the publishing supervisor listens to every platform queue', function () {
    config(['trypost.platforms.x.enabled' => false]);
    app(Availability::class)->set(Platform::X->availabilityKey(), false);

    expect(config('horizon.defaults.social-publishing.queue'))
        ->toBe(Platform::allQueues())
        ->toContain('social-x', 'social-linkedin', 'social-google_business');
});

test('a platform switched off in Availability keeps its queue supervised', function () {
    app(Availability::class)->set(Platform::LinkedIn->availabilityKey(), false);

    expect(config('horizon.defaults.social-publishing.queue'))
        ->toBe(Platform::allQueues())
        ->toContain('social-linkedin');
});
