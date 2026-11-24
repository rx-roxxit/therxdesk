<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AskTheDesk\AskTheDeskRole;
use Database\Factories\AskTheDeskMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One message of the System admin's Ask the Desk transcript. The transcript
 * names no Account, User or Workspace: the admin app sends page aggregates,
 * never customer rows.
 */
class AskTheDeskMessage extends Model
{
    /** @use HasFactory<AskTheDeskMessageFactory> */
    use HasFactory, HasUuids;

    public const int RETENTION_DAYS = 30;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'content',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AskTheDeskRole::class,
        ];
    }
}
