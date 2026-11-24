<?php

declare(strict_types=1);

use App\Enums\Post\PublishStatus;
use App\Models\Account;
use App\Models\AiCreditUsage;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

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

/**
 * An Owner Account with a Workspace and its own Quota, ready for media.
 *
 * @return array{account: Account, workspace: Workspace}
 */
function overviewOwner(string $name, int $quota, ?string $email = null): array
{
    $owner = User::factory()->create(['email' => $email ?? fake()->unique()->safeEmail()]);
    $account = $owner->account->fresh();
    $account->update(['name' => $name]);
    $account->forceFill(['quota_bytes' => $quota])->save();

    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    return ['account' => $account->fresh(), 'workspace' => $workspace];
}

test('the publishing series is 14 zero-filled days ending today with published and failed counts', function () {
    $today = CarbonImmutable::now('UTC')->startOfDay();
    $this->travelTo($today->addHours(12));

    $start = $today->subDays(13);

    Post::factory()->published()->create(['published_at' => $start]);
    Post::factory()->create([
        'publish_status' => PublishStatus::Failed,
        'publication_updated_at' => $start,
    ]);

    Post::factory()->published()->create(['published_at' => $today->subDays(7)]);
    Post::factory()->deletedOnNetwork()->create(['published_at' => $today->subDays(7)]);

    Post::factory()->published()->create(['published_at' => $start->subDay()]);
    Post::factory()->create([
        'publish_status' => PublishStatus::Failed,
        'publication_updated_at' => $start->subDay(),
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.overview'))
        ->assertOk();

    $series = collect($response->json('publishing'))->keyBy('date');

    expect($response->json('publishing'))->toHaveCount(14)
        ->and($response->json('publishing.0.date'))->toBe($start->toDateString())
        ->and($response->json('publishing.13.date'))->toBe($today->toDateString())
        ->and($series[$start->toDateString()])->toBe([
            'date' => $start->toDateString(),
            'published' => 1,
            'failed' => 1,
        ])
        ->and($series[$today->subDays(7)->toDateString()]['published'])->toBe(2)
        ->and($series[$today->subDay()->toDateString()])->toBe([
            'date' => $today->subDay()->toDateString(),
            'published' => 0,
            'failed' => 0,
        ])
        ->and($series->has($start->subDay()->toDateString()))->toBeFalse();
});

test('the usage block sums every Owner Account storage and quota', function () {
    $small = overviewOwner('Small Co', 1000);
    Media::factory()->temporaryUpload($small['workspace'])->create(['size' => 300]);

    $big = overviewOwner('Big Co', 3000);
    Media::factory()->temporaryUpload($big['workspace'])->create(['size' => 800]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.overview'))
        ->assertOk()
        ->assertJsonPath('usage.storage.used_bytes', 1100)
        ->assertJsonPath('usage.storage.quota_bytes', 4000)
        ->assertJsonPath('usage.storage.percent', 27.5);
});

test('the usage block sums the AI allowance and use of every Owner Account', function () {
    $plan = Plan::factory()->create(['ai_credits_per_month' => 100]);

    $onPlan = User::factory()->create()->account->fresh();
    $onPlan->update(['plan_id' => $plan->id]);
    AiCreditUsage::factory()->create([
        'account_id' => $onPlan->id,
        'credits' => 40,
        'created_at' => now(),
    ]);

    $overridden = User::factory()->create()->account->fresh();
    $overridden->update(['ai_credits_per_month' => 50]);
    AiCreditUsage::factory()->create([
        'account_id' => $overridden->id,
        'credits' => 10,
        'created_at' => now(),
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.overview'))
        ->assertOk()
        ->assertJsonPath('usage.ai_credits.used', 50)
        ->assertJsonPath('usage.ai_credits.allowance', 150)
        ->assertJsonPath('usage.ai_credits.percent', 33.3);
});

test('the largest accounts are the ten storing the most, largest first', function () {
    $accounts = [];

    for ($i = 1; $i <= 11; $i++) {
        $owner = overviewOwner("Owner {$i}", 20000);
        Media::factory()->temporaryUpload($owner['workspace'])->create(['size' => $i * 100]);
        $accounts[$i] = $owner['account'];
    }

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.overview'))
        ->assertOk();

    expect($response->json('largest_accounts'))->toHaveCount(10)
        ->and($response->json('largest_accounts.0.account_id'))->toBe($accounts[11]->id)
        ->and($response->json('largest_accounts.0.name'))->toBe('Owner 11')
        ->and($response->json('largest_accounts.0.used_bytes'))->toBe(1100)
        ->and($response->json('largest_accounts.0.quota_bytes'))->toBe(20000)
        ->and($response->json('largest_accounts.0.percent'))->toBe(5.5)
        ->and(collect($response->json('largest_accounts'))->pluck('account_id')->all())
        ->not->toContain($accounts[1]->id);
});

test('a zero quota and a zero AI allowance report a zero percent', function () {
    overviewOwner('No Quota', 0);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.overview'))
        ->assertOk()
        ->assertJsonPath('usage.storage.percent', 0)
        ->assertJsonPath('usage.ai_credits.percent', 0)
        ->assertJsonPath('largest_accounts.0.percent', 0);
});

test('the overview carries no post content and no owner email', function () {
    $owner = overviewOwner('Privacy Co', 10000, 'bigcustomer@example.com');
    Media::factory()->temporaryUpload($owner['workspace'])->create(['size' => 500]);

    Post::factory()->published()->create([
        'content' => 'TOP SECRET POST COPY',
        'published_at' => CarbonImmutable::now('UTC'),
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.overview'))
        ->assertOk();

    expect($response->getContent())
        ->not->toContain('bigcustomer@example.com')
        ->not->toContain('TOP SECRET POST COPY');

    expect(array_keys($response->json('largest_accounts.0')))
        ->toBe(['account_id', 'name', 'used_bytes', 'quota_bytes', 'percent']);

    expect(array_keys($response->json('publishing.0')))
        ->toBe(['date', 'published', 'failed']);
});

test('a site token cannot read the overview', function () {
    $this->withHeaders(['Authorization' => 'Bearer test-site-token'])
        ->getJson(route('internal.overview'))
        ->assertNotFound();
});

test('the overview needs the admin token and its acting admin', function () {
    $this->getJson(route('internal.overview'))->assertNotFound();

    $this->withHeaders(['Authorization' => 'Bearer wrong-token'])
        ->getJson(route('internal.overview'))
        ->assertNotFound();

    $this->withHeaders(['Authorization' => 'Bearer test-admin-token'])
        ->getJson(route('internal.overview'))
        ->assertUnprocessable();
});
