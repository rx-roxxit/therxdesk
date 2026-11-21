<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('interval')->default(Interval::Monthly->value);
            $table->unsignedInteger('amount');
            $table->string('stripe_price_id')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('quota_bytes')->nullable();
            $table->unsignedInteger('ai_credits_per_month')->nullable();
            $table->dropColumn(['stripe_monthly_price_id', 'stripe_yearly_price_id']);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_monthly_price_id')->nullable();
            $table->string('stripe_yearly_price_id')->nullable();
            $table->dropColumn(['quota_bytes', 'ai_credits_per_month']);
        });

        Schema::dropIfExists('plan_prices');
    }
};
