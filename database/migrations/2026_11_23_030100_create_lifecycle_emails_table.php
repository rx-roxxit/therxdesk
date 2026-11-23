<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per lifecycle email actually sent, so each one goes out once per
     * recipient and the System admin can see what a Customer was sent.
     */
    public function up(): void
    {
        Schema::create('lifecycle_emails', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['account_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_emails');
    }
};
