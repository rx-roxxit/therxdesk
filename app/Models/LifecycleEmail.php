<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Account\LifecycleEmailType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lifecycle email that was sent, one row per recipient and type.
 */
class LifecycleEmail extends Model
{
    use HasUuids;

    protected $fillable = [
        'account_id',
        'user_id',
        'type',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LifecycleEmailType::class,
            'sent_at' => 'datetime',
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
}
