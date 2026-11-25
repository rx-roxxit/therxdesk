<?php

declare(strict_types=1);

use App\Ai\Agents\IdeaGenerator;
use App\Ai\Agents\MediaAltTextGenerator;
use App\Ai\Agents\PostContentShortener;
use App\Ai\Agents\PostWritingAssistant;
use App\Enums\Ai\AiFeature;
use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\Ai\AiCreditsExhausted;
use App\Mail\AdminAlert as AdminAlertMail;
use App\Models\Account;
use App\Models\AiCreditUsage;
use App\Models\CatalogSetting;
use App\Models\Media;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiCredits;
use App\Services\Repurpose\CaptionAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    $this->plan = Plan::factory()->create([
        'ai_credits_per_month' => 1000,
        'workspace_limit' => null,
    ]);

    $this->owner = User::factory()->create();
    $this->account = $this->owner->account->fresh();
    $this->account->update(['plan_id' => $this->plan->id]);
    $this->account = $this->account->fresh();
    subscribeAccount($this->account);

    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->owner = $this->owner->fresh();

    $this->credits = app(AiCredits::class);
});

function seedCreditUsage(Account $account, int $credits): AiCreditUsage
{
    return AiCreditUsage::factory()->create([
        'account_id' => $account->id,
        'feature' => AiFeature::WritingAssistant,
        'credits' => $credits,
    ]);
}

function assistPayload(): array
{
    return [
        'mode' => 'generate',
        'prompt' => 'announce our summer launch today',
    ];
}

test('a faked call writes one ledger row and returns the remaining credits', function () {
    CatalogSetting::current()->forceFill(['tokens_per_credit' => 100])->save();

    PostWritingAssistant::fake([
        new TextResponse(
            'A caption',
            new Usage(promptTokens: 200, completionTokens: 100),
            new Meta('anthropic', 'test-model'),
        ),
    ]);

    $this->actingAs($this->owner)
        ->postJson(route('app.posts.ai.assist'), assistPayload())
        ->assertOk()
        ->assertJsonPath('content', 'A caption')
        ->assertJsonPath('ai_credits.total', 1000)
        ->assertJsonPath('ai_credits.remaining', 997)
        ->assertJsonPath('ai_credits.low', false);

    $row = AiCreditUsage::query()->sole();

    expect($row->account_id)->toBe($this->account->id)
        ->and($row->user_id)->toBe($this->owner->id)
        ->and($row->workspace_id)->toBe($this->workspace->id)
        ->and($row->feature)->toBe(AiFeature::WritingAssistant)
        ->and($row->model)->toBe('test-model')
        ->and($row->prompt_tokens)->toBe(200)
        ->and($row->completion_tokens)->toBe(100)
        ->and($row->total_tokens)->toBe(300)
        ->and($row->credits)->toBe(3);
});

test('a reservation holds credits until the real usage replaces it', function () {
    CatalogSetting::current()->forceFill(['tokens_per_credit' => 100])->save();

    $reservation = $this->credits->reserve(
        $this->account,
        $this->owner,
        $this->workspace,
        AiFeature::WritingAssistant,
        22,
    );

    expect($reservation)->not->toBeNull()
        ->and($this->credits->remaining($this->account))->toBe(978);

    $this->credits->reconcile($reservation, new Usage(promptTokens: 200, completionTokens: 100), 'test-model');

    $row = $reservation->fresh();

    expect($this->credits->remaining($this->account))->toBe(997)
        ->and($row->credits)->toBe(3)
        ->and($row->total_tokens)->toBe(300)
        ->and($row->reserved_at)->toBeNull();
});

test('two parallel reservations cannot both take the last credit', function () {
    seedCreditUsage($this->account, 999);

    $reservation = $this->credits->reserve(
        $this->account,
        $this->owner,
        $this->workspace,
        AiFeature::WritingAssistant,
        1,
    );

    expect($reservation)->not->toBeNull();

    $this->credits->reserve($this->account, $this->owner, $this->workspace, AiFeature::WritingAssistant, 1);
})->throws(AiCreditsExhausted::class);

test('a released reservation gives its credits back', function () {
    $reservation = $this->credits->reserve(
        $this->account,
        $this->owner,
        $this->workspace,
        AiFeature::WritingAssistant,
        10,
    );

    $this->credits->release($reservation);

    expect($this->credits->remaining($this->account))->toBe(1000)
        ->and(AiCreditUsage::query()->count())->toBe(0);
});

test('a reservation from a call that stopped counting after fifteen minutes', function () {
    $row = seedCreditUsage($this->account, 10);
    $row->forceFill(['reserved_at' => now()->subMinutes(16)])->save();

    expect($this->credits->remaining($this->account))->toBe(1000);
});

test('the reservation estimate covers the prompt and a bounded completion', function () {
    CatalogSetting::current()->forceFill(['tokens_per_credit' => 100])->save();

    expect($this->credits->estimate(AiFeature::WritingAssistant, str_repeat('a', 400)))->toBe(22);
});

test('an account with no credits left is refused for the owner and a member', function () {
    PostWritingAssistant::fake(['unused']);
    MediaAltTextGenerator::fake(['unused']);
    IdeaGenerator::fake([['title' => 'T', 'body' => 'B']]);

    seedCreditUsage($this->account, 1000);

    $ownerMessage = __('rxdesk.ai_credits.exhausted_owner', [
        'date' => $this->credits->renewsAt($this->account)
            ->timezone($this->owner->timezone ?: config('app.timezone'))
            ->locale(app()->getLocale())
            ->isoFormat('LL'),
    ]);

    $asset = Media::factory()->temporaryUpload($this->workspace)->create();

    $this->actingAs($this->owner)
        ->postJson(route('app.posts.ai.assist'), assistPayload())
        ->assertStatus(Response::HTTP_PAYMENT_REQUIRED)
        ->assertJsonPath('message', $ownerMessage)
        ->assertJsonPath('code', AiCredits::DENIAL_OWNER);

    $this->actingAs($this->owner)
        ->postJson(route('app.posts.ai.alt-text'), ['media_id' => $asset->id])
        ->assertStatus(Response::HTTP_PAYMENT_REQUIRED)
        ->assertJsonPath('message', $ownerMessage)
        ->assertJsonPath('code', AiCredits::DENIAL_OWNER);

    $this->actingAs($this->owner)
        ->postJson(route('app.create.ideas.generate'), ['business' => 'a', 'audience' => 'b'])
        ->assertStatus(Response::HTTP_PAYMENT_REQUIRED)
        ->assertJsonPath('message', $ownerMessage)
        ->assertJsonPath('code', AiCredits::DENIAL_OWNER);

    PostWritingAssistant::assertNeverPrompted();
    MediaAltTextGenerator::assertNeverPrompted();
    IdeaGenerator::assertNeverPrompted();

    $member = workspaceMember($this->workspace, 'member');

    $this->actingAs($member)
        ->postJson(route('app.posts.ai.assist'), assistPayload())
        ->assertStatus(Response::HTTP_PAYMENT_REQUIRED)
        ->assertJsonPath('message', __('rxdesk.ai_credits.exhausted_member'))
        ->assertJsonPath('code', AiCredits::DENIAL_MEMBER);

    PostWritingAssistant::assertNeverPrompted();
});

test('the shortener truncates silently with no credits left and writes no ledger row', function () {
    seedCreditUsage($this->account, 1000);
    PostContentShortener::fake(['unused']);

    $before = AiCreditUsage::query()->count();
    $caption = str_repeat('palavra ', 2000);

    $result = app(CaptionAdapter::class)->adapt($this->workspace, $this->owner, $caption, Platform::TikTok);

    expect(mb_strlen($result))->toBeLessThanOrEqual(Platform::TikTok->maxContentLength())
        ->and(AiCreditUsage::query()->count())->toBe($before);

    PostContentShortener::assertNeverPrompted();
});

test('a trial account gets the fixed trial allowance for its whole length', function () {
    CatalogSetting::current()->forceFill(['trial_ai_credits' => 750])->save();

    $plan = Plan::factory()->create(['ai_credits_per_month' => null]);
    $owner = User::factory()->create();
    $account = $owner->account->fresh();
    $account->update(['plan_id' => $plan->id]);
    $account = $account->fresh();

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_trial',
        'trial_ends_at' => now()->addDays(3),
        'trial_started_at' => now()->subDay(),
    ]);

    $credits = app(AiCredits::class);
    $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);

    expect($credits->allowance($account))->toBe(750)
        ->and($credits->payload($account)['total'])->toBe(750)
        ->and($credits->periodStart($account)->equalTo($subscription->trial_started_at))->toBeTrue()
        ->and($credits->renewsAt($account)->equalTo($account->activeTrialEndsAt()))->toBeTrue();
});

test('usage from the previous month is not counted after the month rolls over', function () {
    seedCreditUsage($this->account, 900);

    expect($this->credits->remaining($this->account))->toBe(100);

    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addHour());

    expect($this->credits->used($this->account))->toBe(0)
        ->and($this->credits->remaining($this->account))->toBe(1000);
});

test('a plan change reads the new plan credits minus this month use', function () {
    seedCreditUsage($this->account, 300);

    $bigger = Plan::factory()->create(['ai_credits_per_month' => 2000]);
    $this->account->update(['plan_id' => $bigger->id]);

    expect($this->credits->remaining($this->account->fresh()))->toBe(1700);
});

test('a member spends the owners credits', function () {
    CatalogSetting::current()->forceFill(['tokens_per_credit' => 100])->save();

    PostWritingAssistant::fake([
        new TextResponse(
            'Member caption',
            new Usage(promptTokens: 100, completionTokens: 100),
            new Meta('anthropic', 'test-model'),
        ),
    ]);

    $member = workspaceMember($this->workspace, 'member');

    $this->actingAs($member)
        ->postJson(route('app.posts.ai.assist'), assistPayload())
        ->assertOk()
        ->assertJsonPath('ai_credits.remaining', 998);

    $row = AiCreditUsage::query()->sole();

    expect($row->user_id)->toBe($member->id)
        ->and($row->account_id)->toBe($this->account->id)
        ->and($row->credits)->toBe(2)
        ->and($this->credits->remaining($this->account->fresh()))->toBe(998);
});

test('a shortened caption spends credits on the shortener feature', function () {
    CatalogSetting::current()->forceFill(['tokens_per_credit' => 100])->save();

    PostContentShortener::fake([
        new TextResponse(
            'Short caption.',
            new Usage(promptTokens: 60, completionTokens: 40),
            new Meta('anthropic', 'test-model'),
        ),
    ]);

    $result = app(CaptionAdapter::class)->adapt(
        $this->workspace,
        $this->owner,
        str_repeat('palavra ', 2000),
        Platform::TikTok,
    );

    expect($result)->toBe('Short caption.');

    $row = AiCreditUsage::query()->sole();

    expect($row->feature)->toBe(AiFeature::CaptionShortener)
        ->and($row->user_id)->toBe($this->owner->id)
        ->and($row->workspace_id)->toBe($this->workspace->id)
        ->and($row->credits)->toBe(1);
});

test('one credit left still allows a call and the balance may reach zero', function () {
    CatalogSetting::current()->forceFill(['tokens_per_credit' => 100])->save();
    seedCreditUsage($this->account, 999);

    PostWritingAssistant::fake([
        new TextResponse(
            'Last caption',
            new Usage(promptTokens: 100),
            new Meta('anthropic', 'test-model'),
        ),
    ]);

    $this->actingAs($this->owner)
        ->postJson(route('app.posts.ai.assist'), assistPayload())
        ->assertOk()
        ->assertJsonPath('ai_credits.remaining', 0)
        ->assertJsonPath('ai_credits.low', true);

    // MySQL returns a SUM() as a decimal string; the engine-agnostic read casts it.
    expect((int) AiCreditUsage::query()->sum('credits'))->toBe(1000);
});

test('a trial keeps one allowance across a calendar month boundary', function () {
    CatalogSetting::current()->forceFill(['trial_ai_credits' => 750])->save();

    $this->travelTo(CarbonImmutable::now('UTC')->startOfMonth()->addDays(5));

    $trialStart = CarbonImmutable::now('UTC')->startOfMonth()->subDays(3);
    $owner = User::factory()->create();
    $account = $owner->account->fresh();

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_trial',
        'trial_started_at' => $trialStart,
        'trial_ends_at' => $trialStart->addDays(10),
    ]);

    AiCreditUsage::factory()->create([
        'account_id' => $account->id,
        'credits' => 400,
        'created_at' => $trialStart->addMinute(),
    ]);

    $credits = app(AiCredits::class);

    expect($credits->periodStart($account->fresh())->equalTo($trialStart))->toBeTrue()
        ->and($credits->allowance($account->fresh()))->toBe(750)
        ->and($credits->used($account->fresh()))->toBe(400)
        ->and($credits->remaining($account->fresh()))->toBe(350);
});

test('a provider failure spends nothing and raises an urgent alert without naming the provider', function () {
    config([
        'ai.default' => 'anthropic',
        'rxdesk.ai.default_text_model' => 'claude-test-model',
        'ai.providers.anthropic.models.text.default' => 'claude-test-model',
    ]);
    Mail::fake();
    PostWritingAssistant::fake([fn () => throw new ConnectionException('timeout')]);

    $response = $this->actingAs($this->owner)
        ->postJson(route('app.posts.ai.assist'), assistPayload())
        ->assertStatus(Response::HTTP_BAD_GATEWAY)
        ->assertJsonPath('message', __('rxdesk.ai.unavailable'));

    expect(AiCreditUsage::query()->count())->toBe(0)
        ->and($this->credits->used($this->account))->toBe(0);

    Mail::assertQueued(AdminAlertMail::class, function (AdminAlertMail $mail): bool {
        expect($mail->level)->toBe(AlertLevel::Urgent)
            ->and($mail->key)->toBe(AdminAlertKey::AiProviderFailure)
            ->and($mail->context['model'])->toBe('claude-test-model')
            ->and($mail->context['error'])->toBe('timeout')
            ->and($mail->render())->toContain('claude-test-model')
            ->toContain('timeout');

        return true;
    });

    $response->assertDontSee('anthropic')->assertDontSee('claude-test-model');
});
