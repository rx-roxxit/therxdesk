<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A per-Account switch the System admin sets on a reviewer Account: it may
     * connect and publish to networks that are installed but switched off.
     * It never reaches a network the server has no credentials for.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->boolean('review_access')->default(false)->after('suspension_reason');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('review_access');
        });
    }
};
