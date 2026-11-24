<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The System admin's thresholds, one row (id 1): when a network bucket
     * raises its TODAY and URGENT alerts, and when an Account is listed in
     * the digest for using a shared bucket.
     */
    public function up(): void
    {
        Schema::create('network_limit_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('today_percent')->default(70);
            $table->unsignedSmallInteger('urgent_percent')->default(90);
            $table->unsignedSmallInteger('account_digest_percent')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_limit_settings');
    }
};
