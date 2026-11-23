<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when the "Contact the team" email was really sent, so a failed send
     * never marks the conversation as handed off.
     */
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->timestamp('escalated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropColumn('escalated_at');
        });
    }
};
