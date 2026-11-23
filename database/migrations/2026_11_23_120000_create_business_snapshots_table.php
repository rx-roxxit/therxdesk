<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per Account per UTC day, written by the 00:05 job. The Account,
     * Plan and Offer are plain values with no foreign key and no cascade: a
     * snapshot outlives an Account deleted here, so history never disappears.
     * Kept indefinitely, no backfill.
     */
    public function up(): void
    {
        Schema::create('business_snapshots', function (Blueprint $table): void {
            $table->date('date');
            $table->uuid('account_id');
            $table->uuid('plan_id')->nullable();
            $table->string('interval')->nullable();
            $table->unsignedBigInteger('mrr_cents')->default(0);
            $table->string('status');
            $table->uuid('offer_id')->nullable();
            // The subscription's cancellation facts, so churn type and reason
            // counts still read after the Account is deleted.
            $table->string('cancellation_type')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->primary(['date', 'account_id']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_snapshots');
    }
};
