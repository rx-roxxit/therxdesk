<?php

declare(strict_types=1);

use App\Enums\Support\SupportMessageRole;
use App\Enums\User\Locale;
use App\Mail\SupportHandoff;
use App\Models\Plan;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;

function supportHandoffMailConversation(string $planName = 'Standard'): SupportConversation
{
    $owner = User::factory()->create(['locale' => Locale::German]);
    $plan = Plan::factory()->create(['name' => $planName]);
    $owner->account->update(['plan_id' => $plan->id]);

    $conversation = SupportConversation::query()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
        'last_page' => '/schedule',
        'last_message_at' => now(),
    ]);

    SupportMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => SupportMessageRole::User,
        'content' => 'My post will not publish.',
    ]);
    SupportMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => SupportMessageRole::Assistant,
        'content' => 'Check the channel connection on the Channels page.',
    ]);

    return $conversation;
}

test('the hand-off email names the customer and carries the message, the context and the transcript', function () {
    $conversation = supportHandoffMailConversation();

    $mail = new SupportHandoff($conversation, 'customer@example.com', 'Please look at my account.');

    $mail->assertHasSubject('[Support] customer@example.com — Standard');
    $mail->assertHasReplyTo('customer@example.com');
    $mail->assertSeeInHtml('Support hand-off');
    $mail->assertSeeInHtml('Please look at my account.');
    $mail->assertSeeInHtml('Customer email');
    $mail->assertSeeInHtml('customer@example.com');
    $mail->assertSeeInHtml($conversation->account_id);
    $mail->assertSeeInHtml('Standard');
    $mail->assertSeeInHtml('Locale');
    $mail->assertSeeInHtml('/schedule');
    $mail->assertSeeInHtml('My post will not publish.');
    $mail->assertSeeInHtml('Check the channel connection on the Channels page.');
    $mail->assertDontSeeInHtml('rxdesk.support_handoff');
});

test('the hand-off email is English whatever the customer picked', function (Locale $locale) {
    $conversation = supportHandoffMailConversation();
    app()->setLocale($locale->value);

    $mail = new SupportHandoff($conversation, 'customer@example.com', 'Please help.');

    $mail->assertHasSubject('[Support] customer@example.com — Standard');
    $mail->assertSeeInHtml('Support hand-off');
    $mail->assertSeeInHtml('Customer email');
    $mail->assertSeeInHtml('Conversation');
})->with(Locale::cases());

test('the hand-off email says no plan when the account has none', function () {
    $conversation = supportHandoffMailConversation();
    $conversation->account->update(['plan_id' => null]);

    $mail = new SupportHandoff($conversation->fresh(), 'customer@example.com', 'Please help.');

    $mail->assertHasSubject('[Support] customer@example.com — No plan');
    $mail->assertSeeInHtml('No plan');
});
