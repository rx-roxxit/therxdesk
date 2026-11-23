<?php

declare(strict_types=1);

use App\Actions\Account\PurgeAccountData;
use App\Enums\Ai\AiFeature;
use App\Enums\Support\SupportChatCounter;
use App\Enums\Support\SupportFeedback;
use App\Enums\Support\SupportMessageRole;
use App\Models\AiCreditUsage;
use App\Models\SupportChatDailyCounter;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Support\SupportChat\SupportChatCounters;
use Illuminate\Support\Facades\Schema;

test('a conversation and each user message count once', function () {
    $this->freezeTime();

    $conversation = SupportConversation::factory()->create();

    SupportMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => SupportMessageRole::User,
    ]);
    SupportMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => SupportMessageRole::Assistant,
    ]);

    $row = SupportChatDailyCounter::query()->sole();

    expect($row->conversations_started)->toBe(1)
        ->and($row->user_messages)->toBe(1);
});

test('only a Support chat ledger row adds tokens and their list cost', function () {
    $this->freezeTime();

    AiCreditUsage::factory()->create([
        'feature' => AiFeature::SupportChat,
        'model' => 'claude-haiku-5-5',
        'prompt_tokens' => 200,
        'completion_tokens' => 100,
        'total_tokens' => 300,
    ]);

    AiCreditUsage::factory()->create([
        'feature' => AiFeature::WritingAssistant,
        'model' => 'claude-haiku-5-5',
        'prompt_tokens' => 900,
        'completion_tokens' => 900,
        'total_tokens' => 1800,
    ]);

    $row = SupportChatDailyCounter::query()->sole();

    expect($row->prompt_tokens)->toBe(200)
        ->and($row->completion_tokens)->toBe(100)
        ->and($row->total_tokens)->toBe(300)
        ->and($row->list_cost_micros)->toBe(70);
});

test('a vote counts when it changes and not when it repeats', function () {
    $this->freezeTime();

    $answer = SupportMessage::factory()->answer()->create();

    $answer->update(['feedback' => SupportFeedback::Up]);
    $answer->update(['feedback' => SupportFeedback::Up]);

    $row = SupportChatDailyCounter::query()->sole();

    expect($row->feedback_up)->toBe(1)
        ->and($row->feedback_down)->toBe(0);

    $answer->update(['feedback' => SupportFeedback::Down]);
    $answer->update(['feedback' => null]);

    $row = SupportChatDailyCounter::query()->sole();

    expect($row->feedback_up)->toBe(1)
        ->and($row->feedback_down)->toBe(1);
});

test('the counters survive pruning the conversation they counted', function () {
    $old = SupportConversation::factory()->create([
        'last_message_at' => now()->subDays(SupportConversation::RETENTION_DAYS + 1),
        'created_at' => now()->subDays(SupportConversation::RETENTION_DAYS + 1),
    ]);
    $oldDay = $old->created_at->toDateString();

    $this->artisan('rxdesk:prune-support-chat')->assertSuccessful();

    expect(SupportConversation::query()->whereKey($old->id)->exists())->toBeFalse();

    $row = SupportChatDailyCounter::query()->where('date', $oldDay)->sole();

    expect($row->conversations_started)->toBe(1);
});

test('the counters survive deleting the account', function () {
    $this->freezeTime();

    $conversation = SupportConversation::factory()->create();

    PurgeAccountData::execute($conversation->account);

    expect(SupportConversation::query()->whereKey($conversation->id)->exists())->toBeFalse();

    $row = SupportChatDailyCounter::query()->sole();

    expect($row->conversations_started)->toBe(1);
});

test('a counter row holds no account or content', function () {
    $columns = Schema::getColumnListing('support_chat_daily_counters');

    expect($columns)->not->toContain('account_id')
        ->not->toContain('user_id')
        ->not->toContain('conversation_id')
        ->not->toContain('content');
});

test('the reader returns the daily rows in a range', function () {
    $this->freezeTime();

    $counters = app(SupportChatCounters::class);
    $counters->increment(SupportChatCounter::UserMessages, 2, now()->subDays(2));
    $counters->increment(SupportChatCounter::UserMessages, 3, now()->subDay());
    $counters->increment(SupportChatCounter::UserMessages, 4, now());

    $rows = $counters->between(now()->subDays(2), now());

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('user_messages')->all())->toBe([2, 3, 4]);
});
