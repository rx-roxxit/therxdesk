<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same count split by Account, for the daily digest's "who used the
     * shared bucket" section. Cascades with the Account it describes.
     */
    public function up(): void
    {
        Schema::create('account_network_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->string('network');
            $table->date('date');
            $table->unsignedBigInteger('calls')->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'network', 'date']);
            $table->index(['network', 'date', 'calls']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_network_calls');
    }
};
