<?php

declare(strict_types=1);

use App\Enums\User\Locale;

test('a lifecycle email renders its copy with no raw key', function (string $view, string $block, Locale $locale) {
    app()->setLocale($locale->value);

    $html = view("mail.{$view}", [
        'title' => __('rxdesk.lifecycle.'.$block.'.title'),
        'previewText' => __('rxdesk.lifecycle.'.$block.'.preview'),
        'chargeDate' => 'March 1, 2027',
        'amount' => '$11.90',
        'deletionDate' => 'April 1, 2027',
        'billingUrl' => 'https://example.test/billing',
        'planUrl' => 'https://example.test/plan',
        'supportUrl' => 'mailto:support@therxdesk.com',
    ])->render();

    expect($html)->toContain(e(__('rxdesk.lifecycle.'.$block.'.heading')))
        ->and($html)->not->toContain('rxdesk.lifecycle')
        ->and($html)->toContain('dir="'.$locale->direction().'"');
})->with([
    'trial ending' => ['lifecycle-trial-ending', 'trial_ending'],
    'access stopped' => ['lifecycle-access-stopped', 'access_stopped'],
    'deletion in 7 days' => ['lifecycle-deletion-7-days', 'deletion_in_7_days'],
    'deletion in 1 day' => ['lifecycle-deletion-1-day', 'deletion_in_1_day'],
    'member deletion in 7 days' => ['lifecycle-member-deletion-7-days', 'member_deletion_in_7_days'],
])->with([Locale::English, Locale::Arabic]);
