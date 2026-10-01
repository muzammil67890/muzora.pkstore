<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 64)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('customer_name', 120);
            $table->string('customer_email', 255)->index();
            $table->string('customer_phone', 30);
            $table->text('shipping_address');
            $table->string('city', 120);
            $table->string('province', 120);
            $table->string('postal_code', 30);
            $table->text('customer_notes')->nullable();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('shipping_amount_minor');
            $table->unsignedBigInteger('discount_amount_minor')->default(0);
            $table->unsignedBigInteger('total_amount_minor');
            $table->char('currency', 3)->default('PKR');
            // Payment methods are introduced in a later phase, so no payment_methods FK exists yet.
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->string('payment_status', 20)->default('pending');
            $table->string('order_status', 20)->default('pending');
            $table->string('tracking_number', 120)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('stock_restored_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['order_status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 180);
            $table->string('product_sku', 80)->nullable();
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();

            $table->index(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
