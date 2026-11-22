<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The subscription that took an Account's one Trial, kept by the Account id
 * so a second subscription on the same account can never start its own.
 */
class TrialAccount extends Model
{
    protected $table = 'trial_accounts';

    protected $primaryKey = 'account_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'account_id',
        'stripe_subscription_id',
    ];
}
