<?php

declare(strict_types=1);

namespace App\Support\SupportChat;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * The help pages that back the Support chat: `resources/help/*.md`, English,
 * one file per topic. The whole corpus goes into every prompt (no retrieval),
 * so the token test is the guard on prompt size.
 *
 * Each page declares the app routes it may link to in its front matter. The
 * corpus is product documentation: it never states a price, Quota or credit
 * amount.
 */
final class HelpCorpus
{
    public const int CHARS_PER_TOKEN = 4;

    public const int MAX_TOKENS = 40_000;

    /**
     * @var list<array{title: string, body: string, routes: list<string>}>|null
     */
    private ?array $documents = null;

    /**
     * @return list<array{title: string, body: string, routes: list<string>}>
     */
    public function documents(): array
    {
        return $this->documents ??= array_map(
            fn (string $path): array => $this->parse(File::get($path)),
            $this->paths(),
        );
    }

    public function prompt(): string
    {
        return implode("\n\n", array_map(
            fn (array $document): string => $document['body'],
            $this->documents(),
        ));
    }

    /**
     * @return list<string>
     */
    public function routeNames(): array
    {
        return array_values(array_unique(array_merge(
            ...array_map(fn (array $document): array => $document['routes'], $this->documents()),
        )));
    }

    public function estimatedTokens(): int
    {
        return (int) ceil(mb_strlen($this->prompt()) / self::CHARS_PER_TOKEN);
    }

    public function count(): int
    {
        return count($this->documents());
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        return array_values(File::glob(resource_path('help/*.md')) ?: []);
    }

    /**
     * @return array{title: string, body: string, routes: list<string>}
     */
    private function parse(string $markdown): array
    {
        $matched = preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/s', $markdown, $parts);

        if ($matched !== 1) {
            return ['title' => '', 'body' => trim($markdown), 'routes' => []];
        }

        $meta = Yaml::parse($parts[1]);
        $meta = is_array($meta) ? $meta : [];

        return [
            'title' => (string) data_get($meta, 'title', ''),
            'body' => trim($parts[2]),
            'routes' => array_values(array_map(
                fn (mixed $route): string => (string) $route,
                (array) data_get($meta, 'routes', []),
            )),
        ];
    }
}
