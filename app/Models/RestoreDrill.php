<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RestoreDrillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One recorded restore drill. The daily digest asks for another when the
 * latest `performed_at` is older than the configured months.
 */
class RestoreDrill extends Model
{
    /** @use HasFactory<RestoreDrillFactory> */
    use HasFactory;

    protected $fillable = [
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'immutable_datetime',
        ];
    }
}
