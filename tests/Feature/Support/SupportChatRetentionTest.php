<?php

declare(strict_types=1);

use App\Actions\Account\PurgeAccountData;
use App\Actions\Account\PurgeNeverPaidAccount;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\Workspace;

function supportChatFixture(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);
    $workspace->members()->attach($owner->id, membershipPivot('admin'));
    $owner->update(['current_workspace_id' => $workspace->id]);

    $conversation = SupportConversation::query()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
        'last_message_at' => now(),
    ]);

    SupportMessage::factory()->create(['conversation_id' => $conversation->id]);

    return [$owner->fresh(), $conversation];
}

test('pruning deletes conversations idle for 30 days and keeps newer ones', function () {
    [$owner, $fresh] = supportChatFixture();

    $old = SupportConversation::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
        'last_message_at' => now()->subDays(31),
        'created_at' => now()->subDays(31),
    ]);
    $oldMessage = SupportMessage::factory()->create(['conversation_id' => $old->id]);

    $recent = SupportConversation::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
        'last_message_at' => now()->subDays(29),
    ]);

    $emptyOld = SupportConversation::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
        'last_message_at' => null,
        'created_at' => now()->subDays(31),
    ]);

    $this->artisan('rxdesk:prune-support-chat')->assertSuccessful();

    expect(SupportConversation::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(SupportConversation::query()->whereKey($emptyOld->id)->exists())->toBeFalse()
        ->and(SupportConversation::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(SupportConversation::query()->whereKey($fresh->id)->exists())->toBeTrue()
        ->and(SupportMessage::query()->whereKey($oldMessage->id)->exists())->toBeFalse();
});

test('purging an account removes its Support chat transcripts and keeps the Owner', function () {
    [$owner, $conversation] = supportChatFixture();

    PurgeAccountData::execute($owner->account);

    expect(SupportConversation::query()->whereKey($conversation->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($owner->id)->exists())->toBeTrue();
});

test('purging a never-paid signup removes its Support chat transcripts with the account', function () {
    [$owner, $conversation] = supportChatFixture();

    PurgeNeverPaidAccount::execute($owner->account);

    expect(SupportConversation::query()->whereKey($conversation->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($owner->id)->exists())->toBeFalse();
});
