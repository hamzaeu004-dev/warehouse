<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // e.g. purchase.created, transfer.deleted, account.login
            $table->string('action', 50)->index();
            $table->string('subject_type', 30)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // Ready-to-read sentence, saved at the time so it stays true after records change
            $table->string('description', 500);
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }

    // Log what already happened before the activity log existed
    private function backfill(): void
    {
        $rows = [];

        foreach (DB::table('purchases')->get() as $p) {
            $lines = DB::table('purchase_items')->join('items', 'items.id', '=', 'purchase_items.item_id')
                ->where('purchase_id', $p->id)
                ->get(['items.name', 'items.unit', 'purchase_items.qty', 'purchase_items.unit_price', 'purchase_items.total']);
            $rows[] = [
                'action' => 'purchase.created',
                'subject_type' => 'purchase',
                'subject_id' => $p->id,
                'description' => "Purchase #{$p->id} added: ".count($lines).' '.($lines->count() === 1 ? 'item' : 'items').', '.money($p->total).($p->store_name ? " from {$p->store_name}" : ''),
                'properties' => json_encode(['lines' => $lines->map(fn ($l) => [
                    'name' => $l->name, 'qty' => (float) $l->qty, 'unit' => $l->unit, 'rate' => (float) $l->unit_price, 'total' => (float) $l->total,
                ])->all()]),
                'created_at' => $p->created_at,
            ];
        }

        foreach (DB::table('transfers')->join('branches', 'branches.id', '=', 'transfers.branch_id')->get(['transfers.*', 'branches.name as branch']) as $t) {
            $lines = DB::table('transfer_items')->join('items', 'items.id', '=', 'transfer_items.item_id')
                ->where('transfer_id', $t->id)
                ->get(['items.name', 'items.unit', 'transfer_items.qty', 'transfer_items.unit_cost', 'transfer_items.total']);
            $rows[] = [
                'action' => 'transfer.created',
                'subject_type' => 'transfer',
                'subject_id' => $t->id,
                'description' => "Sent ".count($lines).' '.($lines->count() === 1 ? 'item' : 'items')." to {$t->branch} (Transfer #{$t->id}), value ".money($t->total_value),
                'properties' => json_encode(['lines' => $lines->map(fn ($l) => [
                    'name' => $l->name, 'qty' => (float) $l->qty, 'unit' => $l->unit, 'rate' => (float) $l->unit_cost, 'total' => (float) $l->total,
                ])->all()]),
                'created_at' => $t->created_at,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('activity_logs')->insert($chunk);
        }
    }
};
