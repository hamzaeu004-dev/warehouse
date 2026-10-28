<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chicken is bought by weight and by count, like it is invoiced
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('pieces', 12, 3)->nullable()->after('qty');
        });

        // Standard rate charged to branches; invoices start from it
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('sale_rate', 12, 2)->nullable()->after('last_price');
        });

        // Money received from branches against their invoices
        Schema::create('branch_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 16, 2);
            $table->string('method', 30)->default('cash');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_payments');

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('sale_rate');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('pieces');
        });
    }
};
