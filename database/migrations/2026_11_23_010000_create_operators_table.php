<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legal_name_th');
            $table->string('legal_name_latin');
            $table->text('address_th');
            $table->text('address_latin');
            $table->string('tax_id', 13);
            $table->string('vat_id')->nullable();
            $table->string('contact_email');
            $table->string('phone')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index('ended_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
