<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_cards', function (Blueprint $table) {
            $table->string('fingerprint')->primary();
            // No foreign key: the card outlives an account deleted here.
            $table->uuid('account_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ended_at')->nullable();
            $table->timestamp('trial_skipped_at')->nullable();
            $table->timestamp('paid_without_trial_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_type')->nullable();
            $table->string('cancellation_reason')->nullable();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('first_invoice_id')->nullable();
        });

        Schema::table('catalog_settings', function (Blueprint $table) {
            $table->string('stripe_billing_portal_configuration_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_settings', function (Blueprint $table) {
            $table->dropColumn('stripe_billing_portal_configuration_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('first_invoice_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'trial_started_at',
                'trial_ended_at',
                'trial_skipped_at',
                'paid_without_trial_at',
                'cancelled_at',
                'cancellation_type',
                'cancellation_reason',
            ]);
        });

        Schema::dropIfExists('trial_cards');
    }
};
