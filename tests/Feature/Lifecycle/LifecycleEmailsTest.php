<?php

declare(strict_types=1);

use App\Mail\Lifecycle\AccessStopped;
use App\Mail\Lifecycle\DeletionIn1Day;
use App\Mail\Lifecycle\DeletionIn7Days;
use App\Mail\Lifecycle\MemberDeletionIn7Days;
use App\Mail\Lifecycle\TrialEnding;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
});

function cyclingOwner(): User
{
    return User::factory()->create();
}

function trialingSubscription(User $owner, int $days): void
{
    $plan = Plan::query()->where('slug', 'mini')->firstOrFail();

    $owner->account->update(['plan_id' => $plan->id]);
    $owner->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_trial',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_trial',
        'trial_ends_at' => now()->addDays($days),
    ]);
}

function lapsedSubscription(User $owner): void
{
    $owner->account->forceFill(['first_paid_at' => now()->subMonth()])->save();
    $owner->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_lapsed',
        'stripe_status' => 'active',
        'stripe_price' => 'price_lapsed',
        'ends_at' => now()->subDay(),
    ]);
}

test('a trial ending in three days gets the trial email with the amount after the offer', function () {
    Mail::fake();

    $owner = cyclingOwner();
    trialingSubscription($owner, 3);

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(TrialEnding::class, function (TrialEnding $mail) use ($owner): bool {
        return $mail->hasTo($owner->email)
            && ($mail->content()->with['amount'] ?? null) === '$11.90';
    });
});

test('the trial email does not go out before the three day mark or after the trial ended', function (int $days) {
    Mail::fake();

    $owner = cyclingOwner();
    trialingSubscription($owner, $days);

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    expect(Mail::queued(TrialEnding::class))->toHaveCount(0);
})->with([
    'four days before' => [4],
    'already ended' => [-1],
]);

test('a lapse sends access stopped once and the date it will be deleted', function () {
    Mail::fake();

    $owner = cyclingOwner();
    lapsedSubscription($owner);

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();
    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    expect(Mail::queued(AccessStopped::class))->toHaveCount(1);
    expect($owner->account->fresh()->deletion_scheduled_at)->not->toBeNull();
});

test('seven days before deletion the Owner and every Member get their warning', function () {
    Mail::fake();

    $owner = cyclingOwner();
    lapsedSubscription($owner);
    $member = User::factory()->create(['account_id' => $owner->account_id]);

    $owner->account->forceFill(['deletion_scheduled_at' => now()->addDays(7)])->save();

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    expect(Mail::queued(DeletionIn7Days::class))->toHaveCount(1)
        ->and(Mail::queued(MemberDeletionIn7Days::class))->toHaveCount(1);

    Mail::assertQueued(MemberDeletionIn7Days::class, fn (MemberDeletionIn7Days $mail): bool => $mail->hasTo($member->email));
});

test('one day before deletion the Owner gets the last warning', function () {
    Mail::fake();

    $owner = cyclingOwner();
    lapsedSubscription($owner);

    $owner->account->forceFill(['deletion_scheduled_at' => now()->addHours(12)])->save();

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    expect(Mail::queued(DeletionIn1Day::class))->toHaveCount(1)
        ->and(Mail::queued(DeletionIn7Days::class))->toHaveCount(0);
});

test('a never-paid signup gets no email', function () {
    Mail::fake();

    $owner = cyclingOwner();

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('a suspended Account gets no email', function () {
    Mail::fake();

    $owner = cyclingOwner();
    lapsedSubscription($owner);
    $owner->account->forceFill([
        'suspended_at' => now(),
        'suspension_reason' => 'Chargeback',
        'deletion_scheduled_at' => now()->addDays(7),
    ])->save();

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('nothing is sent while the app is self-hosted', function () {
    Mail::fake();
    config(['trypost.self_hosted' => true]);

    $owner = cyclingOwner();
    trialingSubscription($owner, 3);

    $this->artisan('rxdesk:lifecycle')->assertSuccessful();

    Mail::assertNothingQueued();
});
