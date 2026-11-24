<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per network per UTC day, counting the app's own API calls
     * against the daily budget each network's developer console reports.
     * `exhausted_at` is set once when the bucket runs out.
     */
    public function up(): void
    {
        Schema::create('network_call_buckets', function (Blueprint $table): void {
            $table->id();
            $table->string('network');
            $table->date('date');
            $table->unsignedBigInteger('calls')->default(0);
            $table->timestamp('exhausted_at')->nullable();
            $table->timestamps();

            $table->unique(['network', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_call_buckets');
    }
};
