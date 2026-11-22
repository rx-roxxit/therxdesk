<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 2)->nullable();
        });

        Schema::table('accounts', function (Blueprint $table) {
            // The country the completed checkout was billed to, when it is one
            // The RX Desk does not sell in. Recorded once, as a fact.
            $table->string('blocked_country', 2)->nullable();
            $table->timestamp('blocked_country_at')->nullable();
            $table->string('blocked_country_payment_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'blocked_country',
                'blocked_country_at',
                'blocked_country_payment_status',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
