<?php

declare(strict_types=1);

use App\Enums\Support\SupportFeedback;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
});

test('the admin token lists the transcripts with their messages', function () {
    $conversation = SupportConversation::factory()->create(['last_page' => '/schedule']);
    SupportMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'content' => 'How do I connect a channel?',
    ]);
    SupportMessage::factory()->answer()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Open the Channels page.',
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.support.conversations.index'));

    $response->assertOk()
        ->assertJsonPath('data.0.id', $conversation->id)
        ->assertJsonPath('data.0.last_page', '/schedule')
        ->assertJsonPath('data.0.messages.0.content', 'How do I connect a channel?')
        ->assertJsonPath('data.0.messages.1.content', 'Open the Channels page.')
        ->assertJsonPath('meta.per_page', 25);
});

test('the site token cannot read transcripts', function () {
    $this->withHeaders(['Authorization' => 'Bearer test-site-token'])
        ->getJson(route('internal.support.conversations.index'))
        ->assertNotFound();
});

test('transcripts need a token', function () {
    $this->getJson(route('internal.support.conversations.index'))->assertNotFound();
});

test('the endpoint is read-only', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.support.conversations.index'))
        ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);
});

test('the feedback filter keeps only the conversations with that vote', function () {
    $down = SupportConversation::factory()->create();
    SupportMessage::factory()->answer()->feedback(SupportFeedback::Down)->create([
        'conversation_id' => $down->id,
    ]);

    $up = SupportConversation::factory()->create();
    SupportMessage::factory()->answer()->feedback(SupportFeedback::Up)->create([
        'conversation_id' => $up->id,
    ]);

    $none = SupportConversation::factory()->create();
    SupportMessage::factory()->answer()->create(['conversation_id' => $none->id]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.support.conversations.index', ['feedback' => 'down']));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $down->id);
});

test('nothing older than the 30-day retention window is returned', function () {
    $recent = SupportConversation::factory()->create(['last_message_at' => now()->subDay()]);
    $old = SupportConversation::factory()->create([
        'last_message_at' => now()->subDays(SupportConversation::RETENTION_DAYS + 1),
        'created_at' => now()->subDays(SupportConversation::RETENTION_DAYS + 1),
    ]);
    $oldEmpty = SupportConversation::factory()->create([
        'last_message_at' => null,
        'created_at' => now()->subDays(SupportConversation::RETENTION_DAYS + 1),
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.support.conversations.index'));

    $response->assertOk()->assertJsonCount(1, 'data');

    expect(collect($response->json('data'))->pluck('id')->all())
        ->not->toContain($old->id)
        ->not->toContain($oldEmpty->id)
        ->toContain($recent->id);
});

test('transcripts paginate with the configured page size', function () {
    SupportConversation::factory()->count(26)->create();

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.support.conversations.index'));

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonPath('meta.total', 26)
        ->assertJsonCount(25, 'data');
});
