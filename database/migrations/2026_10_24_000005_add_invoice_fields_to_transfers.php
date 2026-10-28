<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transfers are sent to branches as a Commercial Invoice: invoice number,
 * due date/terms, P/O number, and per line both weight (Kgs) and count (Qty).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('contact_person', 100)->nullable()->after('name');
            $table->string('address', 255)->nullable()->after('contact_person');
            $table->string('phone', 30)->nullable()->after('address');
            $table->string('email', 150)->nullable()->after('phone');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->unsignedInteger('invoice_no')->nullable()->unique()->after('id');
            $table->unsignedSmallInteger('due_days')->default(0)->after('transfer_date');
            $table->string('po_no', 50)->nullable()->after('due_days');
        });

        Schema::table('transfer_items', function (Blueprint $table) {
            $table->string('description', 150)->nullable()->after('item_id');
            // Count of pieces/packs ("Qty" on the invoice); qty stays the stock quantity in the item's unit
            $table->decimal('pieces', 12, 3)->nullable()->after('qty');
        });

        // Existing transfers get their id as invoice number
        DB::table('transfers')->whereNull('invoice_no')->update(['invoice_no' => DB::raw('id')]);
    }

    public function down(): void
    {
        Schema::table('transfer_items', function (Blueprint $table) {
            $table->dropColumn(['description', 'pieces']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropUnique(['invoice_no']);
            $table->dropColumn(['invoice_no', 'due_days', 'po_no']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'address', 'phone', 'email']);
        });
    }
};
