<?php

declare(strict_types=1);

use App\Support\PostMediaRules;
use Illuminate\Support\Facades\Validator;

test('hosted media rules require id, path and url', function () {
    $rules = PostMediaRules::hostedRules();

    expect($rules['media.*.id'])->toContain('required')
        ->and($rules['media.*.path'])->toContain('required')
        ->and($rules['media.*.url'])->toContain('required')
        ->and($rules['media.*.url'])->not->toContain('url:http,https');
});

test('api media rules accept one reference per item and drop the snapshot keys', function () {
    $rules = PostMediaRules::rules();

    expect($rules)->toHaveKeys(['media', 'media.*', 'media.*.upload_token', 'media.*.url', 'media.*.id', 'media.*.alt', 'media.*.meta'])
        ->and($rules['media.*.url'])->toContain('url:http,https')
        ->and($rules['media.*.upload_token'])->toContain('uuid')
        ->and($rules)->not->toHaveKeys(['media.*.path', 'media.*.type', 'media.*.mime_type', 'media.*.size', 'media.*.original_filename']);
});

test('api media rules can target a destination list', function () {
    expect(PostMediaRules::rules('destinations.*.media'))->toHaveKeys(['destinations.*.media', 'destinations.*.media.*.upload_token']);
});

test('both variants keep the meta rule so validated() preserves it', function () {
    expect(PostMediaRules::rules())->toHaveKey('media.*.meta')
        ->and(PostMediaRules::hostedRules())->toHaveKey('media.*.meta');
});

test('a post media item may never claim the Drive backup source', function (string $source, bool $passes) {
    $validator = Validator::make(
        ['media' => [['id' => 'a', 'path' => 'p', 'url' => 'u', 'source' => $source]]],
        PostMediaRules::hostedRules(),
    );

    expect($validator->errors()->has('media.0.source'))->toBe(! $passes);
})->with([
    'picker source' => ['google_drive', true],
    'backup source' => ['google_drive_backup', false],
]);
