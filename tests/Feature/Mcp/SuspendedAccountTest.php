<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    $result = createApiTestToken();
    subscribeAccount($result['user']->account);

    $this->user = $result['user'];
    $this->workspace = $result['workspace'];
    $this->token = mcpBearerToken($this->user, $this->workspace)['plain_token'];
});

test('mcp refuses a suspended account', function () {
    $this->user->account->forceFill(['suspended_at' => now()])->save();

    $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
        'Accept' => 'application/json, text/event-stream',
    ])->postJson(route('mcp.trypost'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-03-26',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'Pest', 'version' => '1.0'],
        ],
    ])
        ->assertStatus(Response::HTTP_PAYMENT_REQUIRED)
        ->assertJson(['message' => 'Account suspended, contact support.']);
});
