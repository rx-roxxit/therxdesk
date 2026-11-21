<?php

declare(strict_types=1);

use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use App\Mail\AdminAlert as AdminAlertMail;
use App\Models\NightwatchIssue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Mail::fake();
    config([
        'trypost.self_hosted' => false,
        'rxdesk.nightwatch.webhook_secret' => 'nightwatch-secret',
        'rxdesk.alerts.nightwatch.burst_threshold' => 5,
    ]);

    $this->nightwatch = function (string $event, array $issue, ?string $secret = 'nightwatch-secret'): TestResponse {
        $body = (string) json_encode([
            'event' => $event,
            'payload' => ['issue' => $issue],
        ]);

        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

        if ($secret !== null) {
            $headers['Nightwatch-Signature'] = hash_hmac('sha256', $body, $secret);
        }

        return $this->call(
            'POST',
            route('webhooks.nightwatch'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $body,
        );
    };
});

test('a signed issue.opened records the issue', function () {
    ($this->nightwatch)('issue.opened', [
        'id' => 'issue-1',
        'ref' => 12,
        'type' => 'exception',
        'title' => 'Boom',
        'status' => 'open',
        'priority' => 'high',
        'url' => 'https://nightwatch.test/issues/1',
        'environment' => 'production',
    ])->assertOk();

    $this->assertDatabaseHas('nightwatch_issues', [
        'issue_id' => 'issue-1',
        'title' => 'Boom',
        'environment' => 'production',
    ]);
});

test('the real payload shape stores the environment name', function () {
    $body = (string) json_encode([
        'event' => 'issue.opened',
        'payload' => [
            'issue' => ['id' => 'issue-real', 'title' => 'Boom'],
            'environment' => ['id' => 'env-1', 'name' => 'production'],
        ],
    ]);

    $this->call('POST', route('webhooks.nightwatch'), [], [], [], $this->transformHeadersToServerVars([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Nightwatch-Signature' => hash_hmac('sha256', $body, 'nightwatch-secret'),
    ]), $body)->assertOk();

    expect(NightwatchIssue::query()->where('issue_id', 'issue-real')->firstOrFail()->environment)->toBe('production');
});

test('an unsigned call is refused and records nothing', function () {
    ($this->nightwatch)('issue.opened', ['id' => 'issue-1', 'title' => 'Boom'], null)
        ->assertUnauthorized();

    expect(NightwatchIssue::count())->toBe(0);
});

test('a wrong signature is refused and records nothing', function () {
    ($this->nightwatch)('issue.opened', ['id' => 'issue-1', 'title' => 'Boom'], 'wrong-secret')
        ->assertUnauthorized();

    expect(NightwatchIssue::count())->toBe(0);
});

test('with no secret configured the endpoint answers 404', function () {
    config(['rxdesk.nightwatch.webhook_secret' => null]);

    ($this->nightwatch)('issue.opened', ['id' => 'issue-1', 'title' => 'Boom'])
        ->assertNotFound();

    expect(NightwatchIssue::count())->toBe(0);
});

test('issue.resolved marks the issue resolved', function () {
    ($this->nightwatch)('issue.opened', ['id' => 'issue-1', 'title' => 'Boom', 'status' => 'open'])
        ->assertOk();

    ($this->nightwatch)('issue.resolved', ['id' => 'issue-1', 'title' => 'Boom', 'status' => 'resolved'])
        ->assertOk();

    $issue = NightwatchIssue::query()->where('issue_id', 'issue-1')->firstOrFail();

    expect($issue->status)->toBe('resolved')
        ->and($issue->resolved_at)->not->toBeNull();
});

test('a duplicate issue id stays one row', function () {
    ($this->nightwatch)('issue.opened', ['id' => 'issue-1', 'title' => 'Boom'])->assertOk();
    ($this->nightwatch)('issue.opened', ['id' => 'issue-1', 'title' => 'Boom again'])->assertOk();

    expect(NightwatchIssue::count())->toBe(1)
        ->and(NightwatchIssue::query()->firstOrFail()->title)->toBe('Boom again');
});

test('a burst raises one TODAY alert and a sixth issue within the hour stays quiet', function () {
    foreach (range(1, 5) as $number) {
        ($this->nightwatch)('issue.opened', ['id' => "issue-{$number}", 'title' => "Boom {$number}"])
            ->assertOk();
    }

    Mail::assertQueuedCount(1);
    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Today
        && $mail->key === AdminAlertKey::NightwatchBurst
        && $mail->context['count'] === 5);

    ($this->nightwatch)('issue.opened', ['id' => 'issue-6', 'title' => 'Boom 6'])->assertOk();

    Mail::assertQueuedCount(1);
    expect(NightwatchIssue::count())->toBe(6);
});
