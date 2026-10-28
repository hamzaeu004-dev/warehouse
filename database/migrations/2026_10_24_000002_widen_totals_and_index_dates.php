<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('total', 16, 2)->default(0)->change();
            $table->index('purchase_date');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('total', 16, 2)->change();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->decimal('total_value', 16, 2)->default(0)->change();
            $table->index('transfer_date');
        });

        Schema::table('transfer_items', function (Blueprint $table) {
            $table->decimal('total', 16, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['purchase_date']);
            $table->decimal('total', 14, 2)->default(0)->change();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('total', 14, 2)->change();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex(['transfer_date']);
            $table->decimal('total_value', 14, 2)->default(0)->change();
        });

        Schema::table('transfer_items', function (Blueprint $table) {
            $table->decimal('total', 14, 2)->change();
        });
    }
};
