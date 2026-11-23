<?php

declare(strict_types=1);

use App\Support\SupportChat\HelpCorpus;
use Illuminate\Support\Facades\Route;

test('the help corpus fits the prompt budget', function () {
    $corpus = app(HelpCorpus::class);

    expect($corpus->count())->toBeGreaterThanOrEqual(11)
        ->and($corpus->estimatedTokens())->toBeLessThanOrEqual(HelpCorpus::MAX_TOKENS);
});

test('every help page declares routes that exist and need no parameter', function () {
    $routes = app(HelpCorpus::class)->routeNames();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull("The help corpus names the missing route {$name}.")
            ->and(preg_replace('/\{[^}]*\?\}/', '', $route->uri()))->not->toContain('{');
    }
});

test('the whole corpus goes into the prompt every turn', function () {
    $corpus = app(HelpCorpus::class);
    $prompt = $corpus->prompt();

    foreach ($corpus->documents() as $document) {
        expect($prompt)->toContain($document['body']);
    }
});

test('the corpus states no price, storage amount or credit amount', function () {
    $prompt = app(HelpCorpus::class)->prompt();

    expect($prompt)->not->toMatch('/\$\s?\d/')
        ->not->toMatch('/\b\d+\s*(GB|MB|TB|GiB|MiB)\b/i')
        ->not->toMatch('/\b\d+\s+(AI\s+)?credits?\b/i');
});
