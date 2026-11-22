<?php

declare(strict_types=1);

use App\Enums\Account\AccessDenial;
use App\Enums\User\Locale;
use App\Mail\PostPublishFailed;
use App\Models\Post;
use App\Models\SocialAccount;

test('a blocked post email shows the translated access reason', function (Locale $locale) {
    app()->setLocale($locale->value);

    $account = SocialAccount::factory()->instagram()->create();
    $post = Post::factory()->forAccount($account)->failed()->create([
        'publish_block_reason' => AccessDenial::Suspended->value,
        'error_message' => null,
    ]);

    $html = (new PostPublishFailed($post))->render();

    expect($html)->toContain(__('rxdesk.publishing_block.title'))
        ->and($html)->toContain(__('rxdesk.publishing_block.suspended'))
        ->and($html)->not->toContain(__('mail.post_preview.error'));
})->with([
    'English' => [Locale::English],
    'Portuguese' => [Locale::PortugueseBrazil],
    'Ukrainian' => [Locale::Ukrainian],
]);

test('a lapsed blocked post email shows the lapsed reason', function () {
    $account = SocialAccount::factory()->instagram()->create();
    $post = Post::factory()->forAccount($account)->failed()->create([
        'publish_block_reason' => AccessDenial::Lapsed->value,
        'error_message' => null,
    ]);

    expect((new PostPublishFailed($post))->render())
        ->toContain(__('rxdesk.publishing_block.lapsed'));
});
