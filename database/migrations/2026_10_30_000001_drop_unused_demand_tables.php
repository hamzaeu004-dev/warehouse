<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The branch demand feature was removed, but databases that ran its first
 * version still have the tables. Their foreign keys stop items, branches,
 * batches and invoices from being deleted, so they are dropped here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('demand_items');

        // Other tables (e.g. batches.demand_id) may still point at demands
        if (Schema::hasTable('demands') && DB::getDriverName() === 'mysql') {
            $links = DB::select(
                'SELECT TABLE_NAME AS tbl, COLUMN_NAME AS col, CONSTRAINT_NAME AS fk
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?',
                ['demands']
            );
            foreach ($links as $link) {
                Schema::table($link->tbl, function (Blueprint $table) use ($link) {
                    $table->dropForeign($link->fk);
                    $table->dropColumn($link->col);
                });
            }
        }

        Schema::dropIfExists('demands');
    }

    public function down(): void
    {
        // Not restored: the feature is gone.
    }
};
