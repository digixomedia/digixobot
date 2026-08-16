<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->string('telegram_username')->nullable();
            $table->string('name')->nullable();
            $table->string('customer_number')->unique();
            $table->unsignedBigInteger('wallet_balance_paise')->default(0);
            $table->unsignedBigInteger('total_spend_paise')->default(0);
            $table->text('internal_notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_deal')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->string('validity');
            $table->unsignedBigInteger('price_paise');
            $table->unsignedBigInteger('compare_at_price_paise')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('delivery_method')->nullable();
            $table->string('delivery_estimate')->nullable();
            $table->string('activation_method')->nullable();
            $table->string('warranty')->nullable();
            $table->text('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'is_active', 'display_order']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('paid')->index();
            $table->unsignedBigInteger('total_paise');
            $table->string('purchase_key')->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('plan_name');
            $table->unsignedBigInteger('unit_price_paise');
            $table->unsignedInteger('quantity')->default(1);
            $table->json('fulfilment_data')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->bigInteger('amount_paise');
            $table->unsignedBigInteger('balance_after_paise');
            $table->string('reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->unique();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('telegram_updates');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('customers');
    }
};
