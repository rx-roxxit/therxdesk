<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(fn () => config([
    'trypost.self_hosted' => true,
    'trypost.google_auth_enabled' => false,
    'trypost.github_auth_enabled' => false,
]));

test('an OAuth callback creates no user without an invite or a signup code', function (string $provider, string $driver) {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = "{$provider}-stranger";
    $socialiteUser->name = 'Stranger';
    $socialiteUser->email = "{$provider}-stranger@example.com";

    Socialite::shouldReceive('driver')
        ->with($driver)
        ->andReturn($mock = Mockery::mock());

    $mock->shouldReceive('user')->andReturn($socialiteUser);

    $this->get(route("auth.{$provider}.callback"))->assertNotFound();

    expect(User::where('email', "{$provider}-stranger@example.com")->exists())->toBeFalse();
})->with([
    ['google', 'google-auth'],
    ['github', 'github'],
]);

test('the OAuth signup buttons stay off', function (string $provider) {
    $this->get(route("auth.{$provider}.redirect"))->assertNotFound();
})->with(['google', 'github']);
