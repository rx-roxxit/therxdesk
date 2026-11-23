<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Ai\AiFeature;
use App\Models\Account;
use App\Models\AiCreditUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCreditUsage>
 */
class AiCreditUsageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => null,
            'workspace_id' => null,
            'feature' => AiFeature::WritingAssistant,
            'model' => 'test-model',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'credits' => 1,
        ];
    }
}
