<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * The static Anthropic registry: the published price of each model and whether
 * it accepts image input. The live provider list says which ids exist; this is
 * the source of truth for a price and an image slot. A model the registry does
 * not price is never selectable, so a price is never guessed.
 */
final class AiModelRegistry
{
    public const string SLOT_TEXT = 'text';

    public const string SLOT_IMAGE = 'image';

    /**
     * @return list<string>
     */
    public function slots(): array
    {
        return [self::SLOT_TEXT, self::SLOT_IMAGE];
    }

    /**
     * @return array<string, array{input_price_per_mtok: float, output_price_per_mtok: float, accepts_image_input: bool}>
     */
    public function all(): array
    {
        return (array) config('rxdesk.ai.models', []);
    }

    /**
     * A registry entry, or null when the model is absent or has no confirmed
     * price.
     *
     * @return array{input_price_per_mtok: float, output_price_per_mtok: float, accepts_image_input: bool}|null
     */
    public function find(?string $model): ?array
    {
        if (blank($model)) {
            return null;
        }

        $entry = $this->all()[$model] ?? null;

        if (! is_array($entry) || ! $this->priced($entry)) {
            return null;
        }

        return [
            'input_price_per_mtok' => (float) data_get($entry, 'input_price_per_mtok'),
            'output_price_per_mtok' => (float) data_get($entry, 'output_price_per_mtok'),
            'accepts_image_input' => (bool) data_get($entry, 'accepts_image_input', false),
        ];
    }

    public function isSelectable(string $slot, ?string $model): bool
    {
        if (! in_array($slot, $this->slots(), true)) {
            return false;
        }

        $entry = $this->find($model);

        if ($entry === null) {
            return false;
        }

        if ($slot === self::SLOT_IMAGE) {
            return $entry['accepts_image_input'];
        }

        return true;
    }

    /**
     * The priced registry entries whose ids the live list returned, in registry
     * order.
     *
     * @param  list<string>  $liveIds
     * @return array<string, array{input_price_per_mtok: float, output_price_per_mtok: float, accepts_image_input: bool}>
     */
    public function pricedAmong(array $liveIds): array
    {
        $models = [];

        foreach (array_keys($this->all()) as $id) {
            $id = (string) $id;

            if (! in_array($id, $liveIds, true)) {
                continue;
            }

            $entry = $this->find($id);

            if ($entry !== null) {
                $models[$id] = $entry;
            }
        }

        return $models;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function priced(array $entry): bool
    {
        return is_numeric(data_get($entry, 'input_price_per_mtok'))
            && is_numeric(data_get($entry, 'output_price_per_mtok'));
    }
}
