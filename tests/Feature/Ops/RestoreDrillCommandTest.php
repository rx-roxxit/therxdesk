<?php

declare(strict_types=1);

use App\Models\RestoreDrill;

test('recording a restore drill stores when it was performed', function () {
    $this->artisan('rxdesk:restore-drill')->assertSuccessful();

    $drill = RestoreDrill::query()->sole();

    expect($drill->performed_at->timestamp)->toBeGreaterThanOrEqual(now()->subMinute()->timestamp)
        ->and($drill->performed_at->timestamp)->toBeLessThanOrEqual(now()->timestamp);
});
