<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Owner's Quota in bytes; null means the default Quota from env.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->unsignedBigInteger('quota_bytes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('quota_bytes');
        });
    }
};
