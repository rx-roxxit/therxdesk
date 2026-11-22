<?php

declare(strict_types=1);

use App\Enums\User\Locale;
use App\Mail\PostPublished;
use App\Models\Operator;
use App\Models\Post;

beforeEach(function () {
    Operator::forgetCurrent();
    Operator::query()->delete();
});

afterEach(fn () => Operator::forgetCurrent());

function renderPostPublishedFor(string $locale): string
{
    app()->setLocale($locale);

    $post = Post::factory()->instagram()->published()->create();

    return (new PostPublished($post))->render();
}

test('the operator footer renders in every locale', function (Locale $locale) {
    Operator::factory()->create([
        'legal_name_latin' => 'Acme Operator Co., Ltd.',
        'address_latin' => '1 Test Road, Bangkok',
    ]);

    $html = renderPostPublishedFor($locale->value);

    expect($html)->toContain(__('rxdesk.mail.footer.operator', [
        'name' => 'Acme Operator Co., Ltd.',
        'address' => '1 Test Road, Bangkok',
    ], $locale->value));
})->with(Locale::cases());

test('without an Operator the footer sentence and its paragraph are absent', function () {
    $html = renderPostPublishedFor(Locale::English->value);

    expect($html)->not->toContain('is operated by')
        ->not->toContain('operator-footer');
});

test('the operator footer escapes the name and address', function () {
    Operator::factory()->create([
        'legal_name_latin' => 'Acme <b>Bold</b> <script>alert(1)</script>',
        'address_latin' => '1 Road <b>Bold</b>',
    ]);

    $html = renderPostPublishedFor(Locale::English->value);

    expect($html)->toContain('Acme &lt;b&gt;Bold&lt;/b&gt; &lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('1 Road &lt;b&gt;Bold&lt;/b&gt;')
        ->not->toContain('Acme <b>Bold</b>')
        ->not->toContain('<script>alert(1)</script>');
});

test('every locale defines the operator footer key with its placeholders', function (Locale $locale) {
    $translation = __('rxdesk.mail.footer.operator', [], $locale->value);

    expect($translation)->not->toBe('rxdesk.mail.footer.operator')
        ->and($translation)->toContain(':name')
        ->and($translation)->toContain(':address');
})->with(Locale::cases());
