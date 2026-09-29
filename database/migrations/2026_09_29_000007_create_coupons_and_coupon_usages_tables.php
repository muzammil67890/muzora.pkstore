<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('type', 16);
            // percentage stores an integer from 1 to 100; fixed stores a minor-unit amount.
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('minimum_order_amount_minor')->nullable();
            $table->unsignedBigInteger('maximum_discount_amount_minor')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'expires_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code', 64)->nullable()->after('discount_amount_minor');
            $table->unsignedBigInteger('coupon_id')->nullable()->after('coupon_code');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
            });
        }

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('coupon_code', 64);
            $table->unsignedBigInteger('discount_amount_minor');
            $table->timestamps();

            $table->unique(['coupon_id', 'order_id']);
            $table->index(['coupon_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['coupon_id']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['coupon_id', 'coupon_code']);
        });

        Schema::dropIfExists('coupons');
    }
};
