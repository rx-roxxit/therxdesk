<?php

declare(strict_types=1);

use App\Enums\Billing\OfferReach;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offers become admin-managed Campaigns: a name, a reach, the coupon's
 * discount and duration, and the Plans and intervals it covers. Discount,
 * duration and coverage are locked after creation; the row is never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('slug');
            $table->string('reach')->default(OfferReach::Link->value)->after('name');
            $table->string('discount_type')->nullable()->after('reach');
            $table->decimal('percent_off', 5, 2)->nullable()->after('discount_type');
            $table->unsignedInteger('amount_off')->nullable()->after('percent_off');
            $table->string('duration')->nullable()->after('amount_off');
            $table->unsignedSmallInteger('duration_in_months')->nullable()->after('duration');
            $table->json('plan_ids')->nullable()->after('show_banner');
            $table->json('intervals')->nullable()->after('plan_ids');

            $table->dropColumn(['requires_card', 'allow_promotion_codes', 'first_subscription_only']);
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->boolean('requires_card')->default(true);
            $table->boolean('allow_promotion_codes')->default(false);
            $table->boolean('first_subscription_only')->default(false);

            $table->dropColumn([
                'name',
                'reach',
                'discount_type',
                'percent_off',
                'amount_off',
                'duration',
                'duration_in_months',
                'plan_ids',
                'intervals',
            ]);
        });
    }
};
