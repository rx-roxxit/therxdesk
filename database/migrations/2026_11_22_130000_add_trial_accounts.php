<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_accounts', function (Blueprint $table) {
            $table->uuid('account_id')->primary();
            // No foreign key: the claim outlives an Account deleted here.
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_accounts');
    }
};
