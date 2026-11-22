<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A card that started a Trial, kept by Stripe's fingerprint. It has no
 * foreign key to the account, so a deleted account's cards cannot take
 * another Trial by signing up again.
 */
class TrialCard extends Model
{
    protected $table = 'trial_cards';

    protected $primaryKey = 'fingerprint';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'fingerprint',
        'account_id',
        'stripe_subscription_id',
    ];
}
