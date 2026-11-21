<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Alerts\AdminAlertKey;
use Database\Factories\AdminAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One open or closed alert condition. `key` identifies the condition, so the
 * severity and the title come from `AdminAlertKey` and are never stored.
 */
class AdminAlert extends Model
{
    /** @use HasFactory<AdminAlertFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'is_open',
        'opened_at',
        'last_raised_at',
        'last_notified_at',
        'resolved_at',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'opened_at' => 'datetime',
            'last_raised_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function alertKey(): ?AdminAlertKey
    {
        return AdminAlertKey::tryFrom($this->key);
    }
}
