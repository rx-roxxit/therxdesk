<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Ai\AiFeature;
use App\Observers\AiCreditUsageObserver;
use Database\Factories\AiCreditUsageFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI model call, and the credits it cost. The Account's month is the sum
 * of these rows, so a Member spends the Owner's credits.
 */
#[ObservedBy([AiCreditUsageObserver::class])]
class AiCreditUsage extends Model
{
    /** @use HasFactory<AiCreditUsageFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'user_id',
        'workspace_id',
        'feature',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'credits',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'credits' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
