<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Plan the System admin gave the Account for free, outside Stripe. Its
     * own columns on purpose: `trial_ends_at` is a Trial (Cashier clears it on
     * a Subscription), and this state outlives a Trial.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->timestamp('complimentary_since')->nullable()->after('trial_ends_at');
            $table->timestamp('complimentary_until')->nullable()->after('complimentary_since');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn(['complimentary_since', 'complimentary_until']);
        });
    }
};
