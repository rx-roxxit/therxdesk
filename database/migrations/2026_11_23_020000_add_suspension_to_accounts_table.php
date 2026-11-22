<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Suspension turns the whole Account off until the System admin lifts it.
     * The reason is internal only and never reaches a customer surface.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('complimentary_until');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
