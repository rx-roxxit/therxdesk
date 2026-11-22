<?php

declare(strict_types=1);

namespace App\Actions\Offer;

use App\Models\Account;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RedeemOffer
{
    /**
     * Records that the account used the offer its subscription became active
     * or trialing with. A link Offer was claimed first; a general Offer needs
     * no claim, so the redemption attaches it to the account. Only the first
     * redemption counts, and it uses up one of the Offer's places.
     */
    public static function execute(Account $account, ?string $offerId): void
    {
        if (! Str::isUuid($offerId)) {
            return;
        }

        $offer = Offer::query()->find($offerId);

        if ($offer === null) {
            return;
        }

        // A general Offer was never claimed, so its pivot row is written here;
        // a link Offer already has one. The composite primary key makes the
        // insert idempotent without aborting a surrounding transaction.
        DB::table('account_offer')->insertOrIgnore([
            'account_id' => $account->id,
            'offer_id' => $offer->id,
            'claimed_at' => now(),
            'redeemed_at' => now(),
        ]);

        $account->offers()
            ->wherePivotNull('redeemed_at')
            ->updateExistingPivot($offer->id, ['redeemed_at' => now()]);
    }
}
