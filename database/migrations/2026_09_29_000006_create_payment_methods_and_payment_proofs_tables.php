<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 100)->unique();
            $table->string('type', 30);
            $table->string('account_title', 120)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('mobile_number', 30)->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('type');
        });

        // SQLite cannot add a foreign key to an existing table without rebuilding it;
        // production MySQL receives the FK and test SQLite keeps application-level validation.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('payment_method_id')->references('id')->on('payment_methods')->nullOnDelete();
            });
        }

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('transaction_id', 120)->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->string('screenshot_path', 500);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status', 'submitted_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['payment_method_id']);
            });
        }

        Schema::dropIfExists('payment_methods');
    }
};
