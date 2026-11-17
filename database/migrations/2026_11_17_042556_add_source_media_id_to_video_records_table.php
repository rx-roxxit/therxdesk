<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The file a backed-up video was a copy of (its own id when it was the
     * original), so the other posts of its group reuse its Drive copy.
     */
    public function up(): void
    {
        Schema::table('video_records', function (Blueprint $table): void {
            $table->uuid('source_media_id')->nullable()->index()->after('media_id');
        });
    }

    public function down(): void
    {
        Schema::table('video_records', function (Blueprint $table): void {
            $table->dropIndex(['source_media_id']);
            $table->dropColumn('source_media_id');
        });
    }
};
