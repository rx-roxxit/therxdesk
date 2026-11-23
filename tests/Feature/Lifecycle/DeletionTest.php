<?php

declare(strict_types=1);

use App\Actions\Account\LiftSuspension;
use App\Actions\Account\SyncDeletionClock;
use App\Jobs\Media\DeleteMediaFiles;
use App\Models\Account;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
});

function customerAccount(array $attributes = []): User
{
    $owner = User::factory()->create();
    $account = $owner->account;

    $account->forceFill(array_merge([
        'first_paid_at' => now()->subMonth(),
        'stripe_id' => 'cus_keep',
    ], $attributes))->save();

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_keep',
        'stripe_status' => 'active',
        'stripe_price' => 'price_keep',
        'ends_at' => now()->subDay(),
    ]);

    return $owner->fresh();
}

test('at day 30 the data goes while the Owner, empty Account and Stripe customer stay', function () {
    Storage::fake();

    $owner = customerAccount();
    $account = $owner->account;
    $member = User::factory()->create(['account_id' => $account->id]);
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);
    $media = $workspace->addMedia(UploadedFile::fake()->image('logo.jpg'), 'logo');
    $path = $media->path;

    $account->forceFill(['deletion_scheduled_at' => now()->subMinute()])->save();

    Queue::fake([DeleteMediaFiles::class]);

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    expect(Workspace::find($workspace->id))->toBeNull()
        ->and(Media::find($media->id))->toBeNull()
        ->and(User::find($member->id))->toBeNull()
        ->and(Account::find($account->id))->not->toBeNull()
        ->and(User::find($owner->id))->not->toBeNull();

    $fresh = $account->fresh();

    expect($fresh->stripe_id)->toBe('cus_keep')
        ->and($fresh->data_purged_at)->not->toBeNull()
        ->and($fresh->deletion_scheduled_at)->toBeNull()
        ->and($fresh->canStartTrial())->toBeFalse();

    Queue::assertPushed(
        DeleteMediaFiles::class,
        fn (DeleteMediaFiles $job): bool => in_array($path, $job->paths, true),
    );
});

test('a never-paid signup is removed entirely, User included', function () {
    $owner = User::factory()->create();
    $account = $owner->account;
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);

    $account->forceFill([
        'created_at' => now()->subDays(31),
        'deletion_scheduled_at' => now()->subMinute(),
    ])->save();

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    expect(Account::find($account->id))->toBeNull()
        ->and(User::find($owner->id))->toBeNull()
        ->and(Workspace::find($workspace->id))->toBeNull();
});

test('a Suspended Account is not deleted', function () {
    $owner = customerAccount();
    $account = $owner->account;
    $due = now()->subMinute();

    $account->forceFill([
        'deletion_scheduled_at' => $due,
        'suspended_at' => now()->subDay(),
        'suspension_reason' => 'Chargeback',
    ])->save();

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    expect(Account::find($account->id))->not->toBeNull()
        ->and($account->fresh()->deletion_scheduled_at->timestamp)->toBe($due->timestamp);
});

test('lifting a Suspension adds the frozen time back to the deletion clock', function () {
    $this->freezeTime();

    $owner = customerAccount();
    $account = $owner->account;

    $account->forceFill([
        'suspended_at' => now()->subDays(2),
        'suspension_reason' => 'Chargeback',
        'deletion_scheduled_at' => now()->addDays(10),
    ])->save();

    app(LiftSuspension::class)->execute($account->fresh(), 'admin@example.com');

    expect($account->fresh()->deletion_scheduled_at->timestamp)->toBe(now()->addDays(12)->timestamp);
});

test('the clock is cleared when access returns', function () {
    $owner = User::factory()->create();
    $account = $owner->account;

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_active',
    ]);
    $account->forceFill(['deletion_scheduled_at' => now()->addDays(5)])->save();

    SyncDeletionClock::execute($account->fresh());

    expect($account->fresh()->deletion_scheduled_at)->toBeNull();
});

test('a lapse stores 30 days from now and a never-paid signup 30 days from signup', function () {
    $lapsed = customerAccount();
    SyncDeletionClock::execute($lapsed->account->fresh());

    expect($lapsed->account->fresh()->deletion_scheduled_at->timestamp)
        ->toBe(now()->addDays(Account::DELETION_GRACE_DAYS)->timestamp);

    $newcomer = User::factory()->create();
    $newcomer->account->forceFill(['created_at' => now()->subDays(10)])->save();
    $signedUpAt = $newcomer->account->fresh()->created_at;

    SyncDeletionClock::execute($newcomer->account->fresh());

    expect($newcomer->account->fresh()->deletion_scheduled_at->timestamp)
        ->toBe($signedUpAt->copy()->addDays(Account::DELETION_GRACE_DAYS)->timestamp);
});

test('nothing is deleted while the app is self-hosted', function () {
    config(['trypost.self_hosted' => true]);

    $owner = customerAccount();
    $account = $owner->account;
    $account->forceFill(['deletion_scheduled_at' => now()->subMinute()])->save();

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    expect(Account::find($account->id))->not->toBeNull()
        ->and($account->fresh()->data_purged_at)->toBeNull();
});
