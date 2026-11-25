<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A call reserves its worst-case credits before the model runs, so parallel
 * calls cannot all spend the last credit. The row is reconciled with the real
 * usage afterwards; `reserved_at` marks the rows still being reserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_credit_usages', function (Blueprint $table): void {
            $table->timestamp('reserved_at')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_credit_usages', function (Blueprint $table): void {
            $table->dropColumn('reserved_at');
        });
    }
};
