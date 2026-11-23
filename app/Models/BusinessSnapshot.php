<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BusinessStatus;
use App\Enums\Billing\CancellationType;
use App\Enums\Billing\Interval;
use Database\Factories\BusinessSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One day of an Account's billing state, written by
 * `rxdesk:business-snapshot`. A count of money and state only: the Account,
 * Plan and Offer are plain values with no relation and no cascade, so a
 * snapshot outlives the Account it describes.
 */
class BusinessSnapshot extends Model
{
    /** @use HasFactory<BusinessSnapshotFactory> */
    use HasFactory;

    protected $primaryKey = 'date';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'account_id',
        'plan_id',
        'interval',
        'mrr_cents',
        'status',
        'offer_id',
        'cancellation_type',
        'cancellation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'mrr_cents' => 'integer',
            'interval' => Interval::class,
            'status' => BusinessStatus::class,
            'cancellation_type' => CancellationType::class,
        ];
    }
}
