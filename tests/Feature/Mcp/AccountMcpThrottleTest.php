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
    $this->tokenA = mcpBearerToken($this->user, $this->workspaceA)['plain_token'];

    $this->workspaceB = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspaceB->members()->attach($this->user->id, membershipPivot('admin'));
    $this->tokenB = mcpBearerToken($this->user, $this->workspaceB)['plain_token'];
});

function mcpThrottleCall(object $test, string $token): TestResponse
{
    return $test->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json, text/event-stream',
    ])->postJson(route('mcp.trypost'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'ping',
    ]);
}

function mcpThrottleDiscovery(object $test): TestResponse
{
    return $test->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->withHeaders(['Accept' => 'application/json, text/event-stream'])
        ->postJson(route('mcp.trypost'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);
}

test('two workspaces of one account share one MCP budget', function () {
    for ($request = 0; $request < 30; $request++) {
        mcpThrottleCall($this, $this->tokenA)->assertOk();
    }

    auth()->forgetGuards();

    for ($request = 0; $request < 30; $request++) {
        mcpThrottleCall($this, $this->tokenB)->assertOk();
    }

    auth()->forgetGuards();

    mcpThrottleCall($this, $this->tokenA)
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
});

test('a second account keeps its own MCP budget', function () {
    for ($request = 0; $request < 60; $request++) {
        mcpThrottleCall($this, $this->tokenA)->assertOk();
    }

    auth()->forgetGuards();

    mcpThrottleCall($this, $this->tokenA)
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

    auth()->forgetGuards();

    $other = createApiTestToken();
    $otherToken = mcpBearerToken($other['user'], $other['workspace'])['plain_token'];

    mcpThrottleCall($this, $otherToken)->assertOk();
});

test('REST and MCP spend one budget per account', function () {
    $restToken = passportToken($this->user, $this->workspaceA);

    for ($request = 0; $request < 30; $request++) {
        $this->withHeaders(['Authorization' => "Bearer {$restToken}"])
            ->getJson(route('api.workspace.show'))
            ->assertOk();
    }

    auth()->forgetGuards();

    for ($request = 0; $request < 30; $request++) {
        mcpThrottleCall($this, $this->tokenA)->assertOk();
    }

    auth()->forgetGuards();

    mcpThrottleCall($this, $this->tokenB)
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
});

test('ChatGPT discovery without a token is never throttled', function () {
    fakeChatGptEgressRanges(['203.0.113.0/24']);

    for ($request = 0; $request < 61; $request++) {
        mcpThrottleDiscovery($this)->assertOk();
    }
});
