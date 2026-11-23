<?php

declare(strict_types=1);

use App\Enums\Account\AccessDenial;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\SocialAccount;

test('the API exposes an unavailable block reason', function () {
    ['user' => $user, 'workspace' => $workspace, 'token' => $token] = parityContext();
    $account = SocialAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id]);

    Post::factory()->forAccount($account)->failed()->create([
        'user_id' => $user->id,
        'publish_block_reason' => AccessDenial::Unavailable->value,
    ]);

    $this->withHeaders(parityApi($token))
        ->getJson(route('api.posts.index'))
        ->assertOk()
        ->assertJsonFragment([
            'publish_block_reason' => AccessDenial::Unavailable->value,
            'platform' => Platform::LinkedIn->value,
        ]);
});
