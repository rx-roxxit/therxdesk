<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ask_the_desk_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('role');
            $table->text('content');
            $table->timestamps();

            $table->index(['role', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ask_the_desk_messages');
    }
};
