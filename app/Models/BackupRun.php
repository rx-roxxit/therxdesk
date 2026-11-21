<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BackupRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One successful `rxdesk:backup` run. The health endpoint reports the latest
 * `completed_at`; a failed run leaves no row, so a stale time is visible.
 */
class BackupRun extends Model
{
    /** @use HasFactory<BackupRunFactory> */
    use HasFactory;

    protected $fillable = [
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
        ];
    }
}
