<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The deletion clock is stored, never computed from the subscription's
     * state: it starts when access stops, freezes under a Suspension and is
     * pushed back when the Suspension is lifted. `data_purged_at` marks an
     * Account whose data was deleted while its User and Stripe customer are
     * kept, so a later sweep does not purge it again.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->timestamp('deletion_scheduled_at')->nullable()->after('suspension_reason');
            $table->timestamp('data_purged_at')->nullable()->after('deletion_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn(['deletion_scheduled_at', 'data_purged_at']);
        });
    }
};
