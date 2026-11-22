<?php

declare(strict_types=1);

use App\Enums\Billing\OfferReach;
use App\Models\Offer;
use Database\Seeders\OfferSeeder;

test('seeds the local trial offers and can run again', function () {
    (new OfferSeeder)->run();
    (new OfferSeeder)->run();

    $welcome = Offer::query()->where('slug', 'welcome-trial')->firstOrFail();
    $partner = Offer::query()->where('slug', 'partner-trial')->firstOrFail();

    expect(Offer::query()->orderBy('slug')->pluck('slug')->all())->toBe(['partner-trial', 'welcome-trial'])
        ->and($welcome->reach)->toBe(OfferReach::General)
        ->and($welcome->trial_days)->toBe(14)
        ->and($partner->reach)->toBe(OfferReach::Link)
        ->and($partner->show_banner)->toBeTrue();
});
