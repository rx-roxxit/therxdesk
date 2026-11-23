<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // The Checkout whose facts the other `checkout_*` columns hold, so
            // Processing can tell the current checkout's facts from an older
            // checkout's.
            $table->string('checkout_session_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('checkout_session_id');
        });
    }
};
