<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Support\SupportFeedback;
use App\Enums\Support\SupportMessageRole;
use Database\Factories\SupportMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The answer keeps the Markdown the model wrote; it is rendered and its links
 * filtered when the chat reads it.
 */
class SupportMessage extends Model
{
    /** @use HasFactory<SupportMessageFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'role' => SupportMessageRole::class,
            'feedback' => SupportFeedback::class,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function isAnswer(): bool
    {
        return $this->role === SupportMessageRole::Assistant;
    }
}
