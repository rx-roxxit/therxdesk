<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\AccountPolicy;

beforeEach(function () {
    $this->policy = new AccountPolicy;
    $this->unlimited = Plan::where('slug', 'plus')->firstOrFail();
    $this->limited = Plan::factory()->create([
        'slug' => 'limited',
        'name' => 'Limited',
        'workspace_limit' => 1,
    ]);
    $this->account = Account::factory()->create([
        'plan_id' => $this->unlimited->id,
    ]);
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
});

test('swapPlan allows the account owner', function () {
    subscribeAccount($this->account);

    $response = $this->policy->swapPlan(
        $this->owner,
        $this->account->fresh(),
        $this->unlimited,
    );

    expect($response->allowed())->toBeTrue();
});

test('swapPlan denies a non-owner', function () {
    subscribeAccount($this->account);
    $member = User::factory()->create(['account_id' => $this->account->id]);

    $response = $this->policy->swapPlan(
        $member,
        $this->account->fresh(),
        $this->unlimited,
    );

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe(__('billing.flash.cannot_manage'));
});

test('swapPlan denies when the account is not subscribed', function () {
    $response = $this->policy->swapPlan(
        $this->owner,
        $this->account->fresh(),
        $this->unlimited,
    );

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe(__('billing.flash.subscription_required'));
});

test('swapPlan denies when the account holds more workspaces than the target allows', function () {
    subscribeAccount($this->account);
    Workspace::factory()->count(2)->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);

    $response = $this->policy->swapPlan(
        $this->owner,
        $this->account->fresh(),
        $this->limited,
    );

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe(__('billing.flash.too_many_workspaces', [
        'count' => 2,
        'limit' => 1,
    ]));
});

test('useAi allows when subscribed', function () {
    config()->set('trypost.self_hosted', false);
    Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);
    subscribeAccount($this->account);

    $response = $this->policy->useAi($this->owner, $this->account->fresh());

    expect($response->allowed())->toBeTrue();
});

test('useAi denies when there is no active subscription', function () {
    config()->set('trypost.self_hosted', false);
    Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);

    $response = $this->policy->useAi($this->owner, $this->account->fresh());

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe(__('billing.flash.subscription_required'));
});

test('useAi always allows when self-hosted', function () {
    config()->set('trypost.self_hosted', true);

    $response = $this->policy->useAi($this->owner, $this->account);

    expect($response->allowed())->toBeTrue();
});
