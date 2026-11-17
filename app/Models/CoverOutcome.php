<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Cover\Outcome;
use Database\Factories\CoverOutcomeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether a network took the post's video Cover (one row per post), for the
 * networks that set it after publishing. `attempts` and `last_error` stay on
 * the server; the post details show only the outcome.
 */
class CoverOutcome extends Model
{
    /** @use HasFactory<CoverOutcomeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'post_id',
        'outcome',
        'attempts',
        'last_error',
    ];

    /**
     * @var list<string>
     */
    protected $visible = [
        'id',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => Outcome::class,
            'attempts' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
