<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nightwatch_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_id')->unique();
            $table->integer('ref')->nullable();
            $table->string('type')->nullable();
            $table->string('title');
            $table->string('status')->nullable();
            $table->string('priority')->nullable();
            $table->string('url')->nullable();
            $table->string('environment')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nightwatch_issues');
    }
};
