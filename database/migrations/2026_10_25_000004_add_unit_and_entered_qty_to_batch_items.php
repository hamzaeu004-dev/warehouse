<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_items', function (Blueprint $table) {
            $table->string('unit', 30)->nullable()->after('qty');
            $table->decimal('entered_qty', 12, 3)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('batch_items', function (Blueprint $table) {
            $table->dropColumn(['unit', 'entered_qty']);
        });
    }
};
