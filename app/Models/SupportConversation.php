<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SupportConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    /** @use HasFactory<SupportConversationFactory> */
    use HasFactory, HasUuids;

    /**
     * Matches the database backup window: transcripts never outlive a backup
     * an admin could restore them from.
     */
    public const int RETENTION_DAYS = 30;

    /**
     * How long an idle conversation still opens by itself. "New conversation"
     * always starts another one.
     */
    public const int RESUME_HOURS = 24;

    protected $fillable = [
        'account_id',
        'user_id',
        'last_page',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
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

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id')->orderBy('created_at')->orderBy('id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeResumable(Builder $query, CarbonInterface $since): Builder
    {
        return $query->where('last_message_at', '>=', $since);
    }

    public function scopeExpired(Builder $query, CarbonInterface $before): Builder
    {
        return $query->where(fn (Builder $expired): Builder => $expired
            ->where('last_message_at', '<', $before)
            ->orWhere(fn (Builder $empty): Builder => $empty
                ->whereNull('last_message_at')
                ->where('created_at', '<', $before)));
    }
}
