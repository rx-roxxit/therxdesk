<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Offer a subscription was bought with, copied from its Stripe
     * metadata (`trypost_offer_id`). The metrics read it from our database,
     * never Stripe, and fall back to the redeemed Offer when a subscription
     * predates this column. No foreign key: the Offer is never deleted, and a
     * removed row must not cascade.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->uuid('offer_id')->nullable()->after('stripe_price');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('offer_id');
        });
    }
};
