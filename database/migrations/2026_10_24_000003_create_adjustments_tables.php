<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->date('adjustment_date')->index();
            // wastage, expired, damaged, count, other
            $table->string('reason', 20)->index();
            $table->text('notes')->nullable();
            // Negative when stock was lost, positive when a count found extra
            $table->decimal('total_value', 16, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            // Signed change applied to stock: -2 = removed 2, +1 = added 1
            $table->decimal('qty_change', 12, 3);
            // Only for stock counts: what the system said vs what was counted
            $table->decimal('system_qty', 12, 3)->nullable();
            $table->decimal('counted_qty', 12, 3)->nullable();
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total', 16, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustment_items');
        Schema::dropIfExists('adjustments');
    }
};
