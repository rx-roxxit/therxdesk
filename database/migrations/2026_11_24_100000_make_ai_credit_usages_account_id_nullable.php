<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operator work (Ask the Desk) has no customer Account to bill, but its tokens
 * and list cost still belong in the one ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_credit_usages', function (Blueprint $table): void {
            $table->uuid('account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('ai_credit_usages')->whereNull('account_id')->delete();

        Schema::table('ai_credit_usages', function (Blueprint $table): void {
            $table->uuid('account_id')->nullable(false)->change();
        });
    }
};
