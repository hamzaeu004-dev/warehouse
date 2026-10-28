<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('unit', 30)->default('pcs');
            $table->decimal('stock_qty', 12, 3)->default(0);
            // Weighted average purchase cost, used to value transfers
            $table->decimal('avg_cost', 12, 2)->default(0);
            $table->decimal('last_price', 12, 2)->nullable();
            $table->decimal('min_stock', 12, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->date('purchase_date');
            $table->string('store_name')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('transfer_date');
            $table->text('notes')->nullable();
            $table->decimal('total_value', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
    }
};
