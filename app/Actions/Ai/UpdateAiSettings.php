<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Exceptions\Ai\AiCostWarning;
use App\Models\CatalogSetting;
use App\Services\Ai\AiCostTable;
use App\Services\Ai\AiModelRegistry;
use App\Services\Ai\AiSettings;
use App\Services\Catalog\Catalog;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of the AI settings. A changed model is proved with one real
 * call before anything is saved, and a change that pushes an active Plan past
 * 10% of its discounted monthly price stops for a `confirm: true`.
 */
final class UpdateAiSettings
{
    public function __construct(
        private readonly AiSettings $settings,
        private readonly AiCostTable $costTable,
        private readonly ProbeAiModel $probe,
        private readonly Catalog $catalog,
        private readonly AiModelRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function execute(array $values, string $actor): CatalogSetting
    {
        $current = CatalogSetting::current();

        $tokens = (int) data_get($values, 'tokens_per_credit', $current->tokens_per_credit);
        $trial = (int) data_get($values, 'trial_ai_credits', $current->trial_ai_credits);

        $storedText = array_key_exists('text_model', $values)
            ? $this->nullableModel(data_get($values, 'text_model'))
            : $current->text_model;
        $storedImage = array_key_exists('image_model', $values)
            ? $this->nullableModel(data_get($values, 'image_model'))
            : $current->image_model;

        $effectiveText = filled($storedText) ? $storedText : (string) config('rxdesk.ai.default_text_model');
        $effectiveImage = filled($storedImage) ? $storedImage : (string) config('rxdesk.ai.default_image_model');

        $this->assertSelectable(AiModelRegistry::SLOT_TEXT, $effectiveText, 'text_model');
        $this->assertSelectable(AiModelRegistry::SLOT_IMAGE, $effectiveImage, 'image_model');

        $currentText = $this->settings->textModel();
        $currentImage = $this->settings->imageModel();

        if ($effectiveText !== $currentText) {
            $this->probe->execute($effectiveText);
        }

        if ($effectiveImage !== $currentImage) {
            $this->probe->execute($effectiveImage);
        }

        $before = $this->costTable->build($currentText, (int) $current->tokens_per_credit);
        $after = $this->costTable->build($effectiveText, $tokens);

        if (! (bool) data_get($values, 'confirm', false) && $this->crossesThreshold($before, $after)) {
            throw new AiCostWarning($after);
        }

        return $this->catalog->saveAiSettings([
            'tokens_per_credit' => $tokens,
            'trial_ai_credits' => $trial,
            'text_model' => $storedText,
            'image_model' => $storedImage,
        ], $actor);
    }

    /**
     * A Plan that passes the 10% line in the after-table and did not in the
     * before-table is the one that asks for confirmation.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function crossesThreshold(array $before, array $after): bool
    {
        $wasOver = collect(data_get($before, 'plans', []))
            ->keyBy('id')
            ->map(fn (array $row): bool => (bool) $row['over_threshold']);

        return collect(data_get($after, 'plans', []))->contains(function (array $row) use ($wasOver): bool {
            if (! $row['over_threshold']) {
                return false;
            }

            return ! ($wasOver[$row['id']] ?? false);
        });
    }

    private function assertSelectable(string $slot, string $model, string $field): void
    {
        if ($this->registry->isSelectable($slot, $model)) {
            return;
        }

        $label = $slot === AiModelRegistry::SLOT_IMAGE ? 'image' : 'text';

        throw ValidationException::withMessages([
            $field => "That model is not available for the {$label} slot.",
        ]);
    }

    private function nullableModel(mixed $model): ?string
    {
        if (blank($model)) {
            return null;
        }

        return (string) $model;
    }
}
