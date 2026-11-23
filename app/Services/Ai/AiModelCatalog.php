<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Exceptions\Ai\AiModelListingFailed;
use Illuminate\Support\Facades\Http;

/**
 * The live Anthropic model list: which ids exist and their display names. The
 * laravel/ai SDK does not list models, so this walks `GET /models` by
 * `has_more` / `last_id`. It never decides prices or capabilities; the registry
 * does.
 */
final class AiModelCatalog
{
    /**
     * @return array<string, string> model id => display name
     */
    public function live(): array
    {
        $provider = (string) config('ai.default');
        $key = (string) config("ai.providers.{$provider}.key");
        $url = rtrim((string) config("ai.providers.{$provider}.url"), '/');

        if (blank($key) || blank($url)) {
            throw AiModelListingFailed::missingKey();
        }

        $models = [];
        $after = null;

        do {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])->acceptJson()->get($url.'/models', array_filter([
                'limit' => 100,
                'after_id' => $after,
            ]));

            if (! $response->successful()) {
                throw AiModelListingFailed::because($response->status());
            }

            foreach ((array) $response->json('data', []) as $item) {
                $id = data_get($item, 'id');

                if (filled($id)) {
                    $models[(string) $id] = (string) data_get($item, 'display_name', $id);
                }
            }

            $hasMore = (bool) $response->json('has_more', false);
            $after = $response->json('last_id');
        } while ($hasMore && filled($after));

        return $models;
    }
}
