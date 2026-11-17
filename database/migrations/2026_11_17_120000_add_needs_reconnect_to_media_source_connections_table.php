<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_source_connections', function (Blueprint $table): void {
            $table->timestamp('needs_reconnect_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media_source_connections', function (Blueprint $table): void {
            $table->dropColumn('needs_reconnect_at');
        });
    }
};
