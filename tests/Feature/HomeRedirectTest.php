<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('a guest gets the public landing on the home page', function () {
    $this->get(route('app.home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/Landing')
            ->has('languages'));
});

test('home redirects to calendar for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('app.home'));

    $response->assertRedirect(route('app.calendar'));
});
