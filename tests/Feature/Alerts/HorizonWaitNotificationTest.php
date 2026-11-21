<?php

declare(strict_types=1);

use Laravel\Horizon\Horizon;
use Laravel\Horizon\Notifications\LongWaitDetected;

test('Horizon sends no long-wait email', function () {
    expect(Horizon::$email)->toBeNull()
        ->and((new LongWaitDetected('redis', 'default', 60))->via(new stdClass))->toBe([]);
});
