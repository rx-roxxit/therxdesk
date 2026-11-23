<?php

declare(strict_types=1);

use App\Models\Workspace;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    config(['trypost.self_hosted' => true]);

    $result = createApiTestToken();

    $this->user = $result['user'];
    $this->workspaceA = $result['workspace'];
    $this->tokenA = $result['plain_token'];

    $this->workspaceB = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspaceB->members()->attach($this->user->id, membershipPivot('admin'));
    $this->tokenB = passportToken($this->user, $this->workspaceB);
});

/**
 * @param  array<string, mixed>  $headers
 */
function accountThrottleGet(object $test, array $headers): TestResponse
{
    return $test->withHeaders($headers)->getJson(route('api.workspace.show'));
}

test('two workspaces of one account share one API budget', function () {
    for ($request = 0; $request < 30; $request++) {
        accountThrottleGet($this, ['Authorization' => "Bearer {$this->tokenA}"])->assertOk();
    }

    auth()->forgetGuards();

    for ($request = 0; $request < 30; $request++) {
        accountThrottleGet($this, ['Authorization' => "Bearer {$this->tokenB}"])->assertOk();
    }

    auth()->forgetGuards();

    accountThrottleGet($this, ['Authorization' => "Bearer {$this->tokenA}"])
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
});

test('a second account keeps its own API budget', function () {
    for ($request = 0; $request < 60; $request++) {
        accountThrottleGet($this, ['Authorization' => "Bearer {$this->tokenA}"])->assertOk();
    }

    auth()->forgetGuards();

    accountThrottleGet($this, ['Authorization' => "Bearer {$this->tokenA}"])
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

    auth()->forgetGuards();

    $other = createApiTestToken();

    accountThrottleGet($this, ['Authorization' => "Bearer {$other['plain_token']}"])->assertOk();
});
