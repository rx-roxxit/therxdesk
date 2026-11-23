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
            $table->string('billing_country', 2)->nullable();
            $table->string('billing_name')->nullable();
            $table->timestamp('checkout_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'billing_country',
                'billing_name',
                'checkout_completed_at',
            ]);
        });
    }
};
