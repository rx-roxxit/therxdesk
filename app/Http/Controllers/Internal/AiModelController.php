<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Ai\ProbeAiModel;
use App\Http\Requests\Internal\TestAiModelRequest;
use App\Services\Ai\AiModelCatalog;
use App\Services\Ai\AiModelRegistry;
use Illuminate\Http\JsonResponse;

class AiModelController
{
    public function __construct(
        private readonly AiModelCatalog $catalog,
        private readonly AiModelRegistry $registry,
        private readonly ProbeAiModel $probe,
    ) {}

    /**
     * The live provider list filtered to priced registry models, split by the
     * slot each may fill.
     */
    public function index(): JsonResponse
    {
        $live = $this->catalog->live();
        $models = $this->registry->pricedAmong(array_keys($live));

        $text = [];
        $image = [];

        foreach ($models as $id => $entry) {
            $item = [
                'id' => (string) $id,
                'name' => $live[$id] ?? (string) $id,
                'input_price_per_mtok' => $entry['input_price_per_mtok'],
                'output_price_per_mtok' => $entry['output_price_per_mtok'],
                'accepts_image_input' => $entry['accepts_image_input'],
            ];

            $text[] = $item;

            if ($entry['accepts_image_input']) {
                $image[] = $item;
            }
        }

        return response()->json(['text' => $text, 'image' => $image]);
    }

    /**
     * One short real call with the chosen model. Nothing is saved and no credit
     * is spent; a failure answers 422 with the model's error.
     */
    public function test(TestAiModelRequest $request): JsonResponse
    {
        $slot = (string) $request->validated('slot');
        $model = (string) $request->validated('model');

        $this->probe->execute($model);

        return response()->json([
            'ok' => true,
            'slot' => $slot,
            'model' => $model,
        ]);
    }
}
