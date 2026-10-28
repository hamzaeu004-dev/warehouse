<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * unit_cost on an invoice line is the selling rate to the branch. Profit needs
 * what that stock cost the warehouse, so keep the average purchase cost too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_items', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->default(0)->after('unit_cost');
        });

        // Before invoices had their own rate, transfers were valued at average cost
        DB::table('transfer_items')->update(['cost_price' => DB::raw('unit_cost')]);
    }

    public function down(): void
    {
        Schema::table('transfer_items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
