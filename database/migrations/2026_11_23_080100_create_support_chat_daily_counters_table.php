<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per UTC day, written as events happen. It holds no content and
     * no Account id, and it has no foreign key, so pruning or deleting an
     * Account never removes a count.
     */
    public function up(): void
    {
        Schema::create('support_chat_daily_counters', function (Blueprint $table): void {
            $table->date('date')->primary();
            $table->unsignedInteger('conversations_started')->default(0);
            $table->unsignedInteger('user_messages')->default(0);
            $table->unsignedInteger('handoffs')->default(0);
            $table->unsignedInteger('feedback_up')->default(0);
            $table->unsignedInteger('feedback_down')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->unsignedBigInteger('list_cost_micros')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_chat_daily_counters');
    }
};
