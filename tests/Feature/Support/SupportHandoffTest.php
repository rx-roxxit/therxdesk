<?php

declare(strict_types=1);

use App\Enums\Support\SupportMessageRole;
use App\Enums\User\Locale;
use App\Mail\SupportHandoff;
use App\Models\Plan;
use App\Models\SupportChatDailyCounter;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use App\Support\SupportChat\SupportChatGate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'test-key',
        'rxdesk.support.email' => 'support@therxdesk.com',
    ]);

    $this->plan = Plan::factory()->create(['name' => 'Standard']);

    $this->owner = User::factory()->create([
        'email' => 'owner@example.com',
        'locale' => Locale::English,
    ]);

    $this->account = $this->owner->account->fresh();
    $this->account->update(['plan_id' => $this->plan->id]);

    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    subscribeAccount($this->account);

    app(Availability::class)->set(SupportChatGate::AVAILABILITY_KEY, true);

    $this->conversation = SupportConversation::query()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
        'last_page' => '/schedule',
        'last_message_at' => now(),
    ]);

    SupportMessage::query()->create([
        'conversation_id' => $this->conversation->id,
        'role' => SupportMessageRole::User,
        'content' => 'How do I schedule a post?',
    ]);
    SupportMessage::query()->create([
        'conversation_id' => $this->conversation->id,
        'role' => SupportMessageRole::Assistant,
        'content' => 'Open the composer and choose a time.',
    ]);
});

test('a hand-off sends the email with the subject, reply-to and transcript, then marks the conversation', function () {
    Mail::fake();

    $response = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.handoff.store', $this->conversation), [
            'message' => 'My invoice looks wrong.',
            'email' => 'owner@example.com',
            'page' => '/settings/account/billing',
        ])
        ->assertOk();

    $response->assertJsonPath('escalated_at', fn (mixed $value): bool => is_string($value) && $value !== '');

    expect($this->conversation->fresh()->escalated_at)->not->toBeNull()
        ->and($this->conversation->fresh()->last_page)->toBe('/settings/account/billing');

    Mail::assertSent(SupportHandoff::class, function (SupportHandoff $mail): bool {
        $envelope = $mail->envelope();

        expect($envelope->subject)->toBe('[Support] owner@example.com — Standard')
            ->and($envelope->replyTo[0]->address)->toBe('owner@example.com');

        $mail->assertSeeInHtml('My invoice looks wrong.')
            ->assertSeeInHtml($this->account->id)
            ->assertSeeInHtml('Standard')
            ->assertSeeInHtml('/settings/account/billing')
            ->assertSeeInHtml('How do I schedule a post?')
            ->assertSeeInHtml('Open the composer and choose a time.');

        return $mail->hasTo('support@therxdesk.com');
    });
});

test('the support address comes from config', function () {
    config(['rxdesk.support.email' => 'help@example.com']);
    Mail::fake();

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.handoff.store', $this->conversation), [
            'message' => 'Please help.',
            'email' => 'owner@example.com',
        ])
        ->assertOk();

    Mail::assertSent(SupportHandoff::class, fn (SupportHandoff $mail): bool => $mail->hasTo('help@example.com'));
});

test('a failed send returns an error and never marks the conversation', function () {
    $throwing = new class(app('mail.manager')) extends MailFake
    {
        public function send($view, array $data = [], $callback = null): void
        {
            throw new RuntimeException('The mail server is down.');
        }
    };

    Mail::swap($throwing);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.handoff.store', $this->conversation), [
            'message' => 'Please help.',
            'email' => 'owner@example.com',
        ])
        ->assertStatus(Response::HTTP_BAD_GATEWAY)
        ->assertJsonPath('code', 'send_failed');

    expect($this->conversation->fresh()->escalated_at)->toBeNull()
        ->and(SupportChatDailyCounter::query()->sole()->handoffs)->toBe(0);
});

test('another user cannot hand off a conversation', function () {
    Mail::fake();

    $member = workspaceMember($this->workspace, 'member');

    $this->actingAs($member)
        ->postJson(route('app.support.handoff.store', $this->conversation), [
            'message' => 'Let me in.',
            'email' => 'member@example.com',
        ])
        ->assertNotFound();

    Mail::assertNothingSent();
});

test('a hand-off needs a message, a valid email and a page path', function (array $payload, string $field) {
    Mail::fake();

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.handoff.store', $this->conversation), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    Mail::assertNothingSent();
})->with([
    'no message' => [['message' => '', 'email' => 'owner@example.com'], 'message'],
    'no email' => [['message' => 'Help'], 'email'],
    'invalid email' => [['message' => 'Help', 'email' => 'not-an-email'], 'email'],
    'page is not a path' => [['message' => 'Help', 'email' => 'owner@example.com', 'page' => 'https://example.com'], 'page'],
]);

test('the hand-off counter counts once when a conversation is handed off again', function () {
    Mail::fake();

    $send = fn () => $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.handoff.store', $this->conversation), [
            'message' => 'Please help.',
            'email' => 'owner@example.com',
        ])
        ->assertOk();

    $send();
    $send();

    $row = SupportChatDailyCounter::query()->sole();

    expect($row->handoffs)->toBe(1);
});

test('the hand-off endpoint is closed while the chat is switched off', function () {
    Mail::fake();
    app(Availability::class)->set(SupportChatGate::AVAILABILITY_KEY, false);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.handoff.store', $this->conversation), [
            'message' => 'Please help.',
            'email' => 'owner@example.com',
        ])
        ->assertNotFound();

    Mail::assertNothingSent();
});
