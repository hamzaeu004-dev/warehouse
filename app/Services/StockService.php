<?php

namespace App\Services;

use App\Models\Adjustment;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * All stock changes go through here so the item's stock_qty and avg_cost
 * stay in sync with purchase and transfer records.
 */
class StockService
{
    /**
     * @param  array{purchase_date: string, store_name?: ?string, notes?: ?string}  $data
     * @param  array<int, array{item_id: int, qty: float|string, unit_price: float|string}>  $lines
     */
    public function recordPurchase(array $data, array $lines): Purchase
    {
        return DB::transaction(function () use ($data, $lines) {
            $purchase = Purchase::create([
                'purchase_date' => $data['purchase_date'],
                'store_name' => $data['store_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total' => 0,
            ]);

            $total = 0;

            foreach ($lines as $line) {
                $item = Item::lockForUpdate()->findOrFail($line['item_id']);
                $qty = (float) $line['qty'];
                $price = (float) $line['unit_price'];
                $lineTotal = round($qty * $price, 2);

                $purchase->lines()->create([
                    'item_id' => $item->id,
                    'qty' => $qty,
                    'pieces' => self::optional($line['pieces'] ?? null),
                    'unit_price' => $price,
                    'total' => $lineTotal,
                ]);

                $oldQty = max((float) $item->stock_qty, 0);
                $newQty = $oldQty + $qty;

                $item->avg_cost = $newQty > 0
                    ? round(($oldQty * (float) $item->avg_cost + $qty * $price) / $newQty, 2)
                    : $price;
                $item->stock_qty = (float) $item->stock_qty + $qty;
                $item->last_price = $price;
                $item->save();

                $total += $lineTotal;
            }

            $purchase->update(['total' => $total]);

            $lines = $purchase->lines()->with('item')->get();
            ActivityLogger::record(
                'purchase.created',
                "Purchase #{$purchase->id} added: ".ActivityLogger::count($lines->count()).', '.money($total).($purchase->store_name ? " from {$purchase->store_name}" : ''),
                $purchase,
                ['lines' => ActivityLogger::lines($lines, 'unit_price')]
            );

            return $purchase;
        });
    }

    /**
     * Corrects a saved purchase (wrong qty, price or item). Only the
     * difference is applied to stock, so a price can be fixed even after
     * some of the stock went to branches; qty can only go down by what is
     * still in the warehouse.
     */
    public function updatePurchase(Purchase $purchase, array $data, array $lines): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $lines) {
            $old = $purchase->lines()->with('item')->get();
            $before = ActivityLogger::lines($old, 'unit_price');

            // Per item: [qty, value] before and after
            $oldByItem = [];
            foreach ($old as $line) {
                $oldByItem[$line->item_id][0] = ($oldByItem[$line->item_id][0] ?? 0) + (float) $line->qty;
                $oldByItem[$line->item_id][1] = ($oldByItem[$line->item_id][1] ?? 0) + (float) $line->qty * (float) $line->unit_price;
            }
            $newByItem = [];
            foreach ($lines as $line) {
                $id = (int) $line['item_id'];
                $newByItem[$id][0] = ($newByItem[$id][0] ?? 0) + (float) $line['qty'];
                $newByItem[$id][1] = ($newByItem[$id][1] ?? 0) + (float) $line['qty'] * (float) $line['unit_price'];
            }

            $ids = array_unique([...array_keys($oldByItem), ...array_keys($newByItem)]);
            $items = Item::lockForUpdate()->whereIn('id', $ids)->get()->keyBy('id');

            $errors = [];
            foreach ($ids as $id) {
                $change = ($newByItem[$id][0] ?? 0) - ($oldByItem[$id][0] ?? 0);
                if ((float) $items[$id]->stock_qty + $change < -0.0005) {
                    $errors[] = "{$items[$id]->name}: only ".qty($items[$id]->stock_qty)." {$items[$id]->unit} left in the warehouse, the rest was already sent or used. Quantity can go down by that much at most.";
                }
            }
            if ($errors) {
                throw ValidationException::withMessages(['lines' => $errors]);
            }

            foreach ($ids as $id) {
                $item = $items[$id];
                [$oldQty, $oldValue] = $oldByItem[$id] ?? [0, 0];
                [$newQty, $newValue] = $newByItem[$id] ?? [0, 0];

                $stock = (float) $item->stock_qty;
                $newStock = $stock - $oldQty + $newQty;
                $value = $stock * (float) $item->avg_cost - $oldValue + $newValue;

                if ($newStock > 0.0005) {
                    $item->avg_cost = max(round($value / $newStock, 2), 0);
                }
                $item->stock_qty = $newStock;
                $item->save();
            }

            $purchase->lines()->delete();
            $total = 0;
            foreach ($lines as $line) {
                $lineTotal = round((float) $line['qty'] * (float) $line['unit_price'], 2);
                $purchase->lines()->create([
                    'item_id' => $line['item_id'],
                    'qty' => (float) $line['qty'],
                    'pieces' => self::optional($line['pieces'] ?? null),
                    'unit_price' => (float) $line['unit_price'],
                    'total' => $lineTotal,
                ]);
                $total += $lineTotal;
            }

            $purchase->update([
                'purchase_date' => $data['purchase_date'],
                'store_name' => $data['store_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total' => $total,
            ]);

            // Last price = the latest purchase line for each touched item
            foreach ($items as $item) {
                $item->update(['last_price' => $item->purchaseItems()->latest('id')->value('unit_price')]);
            }

            $after = ActivityLogger::lines($purchase->lines()->with('item')->get(), 'unit_price');
            ActivityLogger::record(
                'purchase.updated',
                "Purchase #{$purchase->id} edited: ".money(array_sum(array_column($before, 'total'))).' → '.money($total).'. '.self::describeLineChanges($before, $after),
                $purchase,
                ['lines' => $after, 'before' => $before]
            );

            return $purchase->fresh();
        });
    }

    public function deletePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            foreach ($purchase->lines as $line) {
                $item = Item::lockForUpdate()->findOrFail($line->item_id);
                $qty = (float) $line->qty;

                if ((float) $item->stock_qty < $qty) {
                    throw ValidationException::withMessages([
                        'purchase' => "Some {$item->name} from this purchase has already been sent to branches, so it cannot be deleted.",
                    ]);
                }

                $remaining = (float) $item->stock_qty - $qty;
                if ($remaining > 0) {
                    $item->avg_cost = max(round(((float) $item->stock_qty * (float) $item->avg_cost - $qty * (float) $line->unit_price) / $remaining, 2), 0);
                }
                $item->stock_qty = $remaining;
                $item->last_price = $item->purchaseItems()
                    ->where('purchase_id', '!=', $purchase->id)
                    ->latest('id')
                    ->value('unit_price');
                $item->save();
            }

            ActivityLogger::record(
                'purchase.deleted',
                "Purchase #{$purchase->id} deleted (".ActivityLogger::count($purchase->lines->count()).', '.money($purchase->total).'). Stock reduced.',
                $purchase,
                ['lines' => ActivityLogger::lines($purchase->lines()->with('item')->get(), 'unit_price')]
            );

            $purchase->delete();
        });
    }

    /**
     * Sends stock to a branch as a Commercial Invoice. `qty` is the stock
     * quantity in the item's unit (the Kgs for chicken) and the amount is
     * qty x rate, rounded to whole rupees like the printed invoice. Without
     * a rate the item's average purchase cost is used.
     *
     * @param  array{branch_id: int, transfer_date: string, invoice_no?: ?int, due_days?: ?int, po_no?: ?string, notes?: ?string}  $data
     * @param  array<int, array{item_id: int, qty: float|string, pieces?: float|string|null, rate?: float|string|null, description?: ?string}>  $lines
     */
    public function recordTransfer(array $data, array $lines): Transfer
    {
        return DB::transaction(function () use ($data, $lines) {
            $fromBatchId = $data['from_batch_id'] ?? null;
            $fromBatch = null;
            $batchQtyByItem = [];

            if ($fromBatchId) {
                $fromBatch = Batch::with('lines')->find($fromBatchId);
                if ($fromBatch) {
                    foreach ($fromBatch->lines as $bl) {
                        $batchQtyByItem[$bl->item_id] = ($batchQtyByItem[$bl->item_id] ?? 0) + (float) $bl->qty;
                    }
                }
            }

            // Check total requested qty per item before touching anything
            $requested = [];
            foreach ($lines as $line) {
                $requested[$line['item_id']] = ($requested[$line['item_id']] ?? 0) + (float) $line['qty'];
            }

            $items = Item::lockForUpdate()->whereIn('id', array_keys($requested))->get()->keyBy('id');

            $errors = [];
            foreach ($requested as $itemId => $qty) {
                $item = $items[$itemId] ?? null;
                if (! $item) {
                    $errors[] = 'Item not found.';
                } else {
                    $alreadyInBatch = $batchQtyByItem[$itemId] ?? 0;
                    $available = (float) $item->stock_qty + $alreadyInBatch;
                    if ($available < $qty) {
                        $errors[] = "{$item->name}: only ".rtrim(rtrim(number_format((float) $item->stock_qty, 3, '.', ''), '0'), '.')." {$item->unit} in stock.";
                    }
                }
            }
            if ($errors) {
                throw ValidationException::withMessages(['lines' => $errors]);
            }

            $invoiceNo = $data['invoice_no'] ?? null;
            if ($invoiceNo && Transfer::where('invoice_no', $invoiceNo)->exists()) {
                throw ValidationException::withMessages(['invoice_no' => "Invoice #{$invoiceNo} already exists."]);
            }

            $transfer = Transfer::create([
                'invoice_no' => $invoiceNo ?: Transfer::nextInvoiceNo(),
                'branch_id' => $data['branch_id'],
                'transfer_date' => $data['transfer_date'],
                'due_days' => (int) ($data['due_days'] ?? 0),
                'po_no' => $data['po_no'] ?? ($fromBatch ? $fromBatch->batch_no : null),
                'notes' => $data['notes'] ?? null,
                'total_value' => 0,
            ]);

            $total = 0;

            foreach ($lines as $line) {
                $item = $items[$line['item_id']];
                $qty = (float) $line['qty'];
                $rate = isset($line['rate']) && $line['rate'] !== '' ? (float) $line['rate'] : (float) $item->avg_cost;
                $lineTotal = round($qty * $rate);

                $transfer->lines()->create([
                    'item_id' => $item->id,
                    'description' => self::optionalText($line['description'] ?? null),
                    'qty' => $qty,
                    'pieces' => self::optional($line['pieces'] ?? null),
                    'unit_cost' => $rate,
                    'cost_price' => (float) $item->avg_cost,
                    'total' => $lineTotal,
                ]);

                // Only deduct quantity that was NOT already deducted by the batch
                $alreadyInBatch = $batchQtyByItem[$item->id] ?? 0;
                $toDeduct = max(0, $qty - $alreadyInBatch);
                $batchQtyByItem[$item->id] = max(0, $alreadyInBatch - $qty);

                if ($toDeduct > 0) {
                    $item->stock_qty = (float) $item->stock_qty - $toDeduct;
                    $item->save();
                }

                $total += $lineTotal;
            }

            $transfer->update(['total_value' => $total]);

            if ($fromBatch) {
                $branch = Branch::find($data['branch_id']);
                $branchName = $branch ? $branch->name : 'Branch';
                $fromBatch->update([
                    'notes' => trim(($fromBatch->notes ? $fromBatch->notes . ' · ' : '') . "Sent to {$branchName} (Invoice #{$transfer->invoice_no})"),
                ]);
            }

            $lines = $transfer->lines()->with('item')->get();
            ActivityLogger::record(
                'transfer.created',
                "Invoice #{$transfer->invoice_no}: sent ".ActivityLogger::count($lines->count())." to {$transfer->branch->name}, amount ".money($total).($fromBatch ? " (from batch {$fromBatch->batch_no})" : ''),
                $transfer,
                ['lines' => ActivityLogger::lines($lines, 'unit_cost')]
            );

            return $transfer;
        });
    }

    /**
     * Wastage/expired/damaged/other remove `qty` from stock. A stock count
     * sets stock to `counted_qty` and records the difference either way.
     * Changes are valued at the item's average cost.
     *
     * @param  array{adjustment_date: string, reason: string, notes?: ?string}  $data
     * @param  array<int, array{item_id: int, qty?: float|string, counted_qty?: float|string}>  $lines
     */
    public function recordAdjustment(array $data, array $lines): Adjustment
    {
        return DB::transaction(function () use ($data, $lines) {
            $isCount = $data['reason'] === 'count';
            $items = Item::lockForUpdate()->whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');

            $changes = [];
            $errors = [];
            foreach ($lines as $line) {
                $item = $items[$line['item_id']];
                $stock = (float) $item->stock_qty;

                if ($isCount) {
                    $change = round((float) $line['counted_qty'] - $stock, 3);
                    if ($change == 0) {
                        continue;
                    }
                } else {
                    $change = -(float) $line['qty'];
                    if ($stock + $change < 0) {
                        $errors[] = "{$item->name}: only ".qty($stock)." {$item->unit} in stock.";
                    }
                }

                $changes[] = ['item' => $item, 'change' => $change, 'counted' => $isCount ? (float) $line['counted_qty'] : null];
            }

            if ($errors) {
                throw ValidationException::withMessages(['lines' => $errors]);
            }
            if (! $changes) {
                throw ValidationException::withMessages(['lines' => 'Counted stock matches the system for every item. Nothing to adjust.']);
            }

            $adjustment = Adjustment::create([
                'adjustment_date' => $data['adjustment_date'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'total_value' => 0,
            ]);

            $total = 0;
            foreach ($changes as ['item' => $item, 'change' => $change, 'counted' => $counted]) {
                $cost = (float) $item->avg_cost;
                $lineTotal = round($change * $cost, 2);

                $adjustment->lines()->create([
                    'item_id' => $item->id,
                    'qty_change' => $change,
                    'system_qty' => $isCount ? (float) $item->stock_qty : null,
                    'counted_qty' => $counted,
                    'unit_cost' => $cost,
                    'total' => $lineTotal,
                ]);

                // Average cost stays the same: removed or found stock is valued at it
                $item->stock_qty = (float) $item->stock_qty + $change;
                $item->save();

                $total += $lineTotal;
            }

            $adjustment->update(['total_value' => $total]);

            $lines = $adjustment->lines()->with('item')->get();
            ActivityLogger::record(
                'adjustment.created',
                "Stock adjusted ({$adjustment->reasonLabel()}): ".ActivityLogger::count($lines->count()).', '.($total < 0 ? 'loss '.money(abs($total)) : 'value '.money($total)).($adjustment->notes ? ". Note: {$adjustment->notes}" : ''),
                $adjustment,
                ['lines' => ActivityLogger::lines($lines, 'unit_cost')]
            );

            return $adjustment;
        });
    }

    public function deleteAdjustment(Adjustment $adjustment): void
    {
        DB::transaction(function () use ($adjustment) {
            foreach ($adjustment->lines as $line) {
                $item = Item::lockForUpdate()->findOrFail($line->item_id);
                $newStock = (float) $item->stock_qty - (float) $line->qty_change;

                if ($newStock < 0) {
                    throw ValidationException::withMessages([
                        'adjustment' => "Stock of {$item->name} added by this adjustment has already been used, so it cannot be deleted.",
                    ]);
                }

                $item->stock_qty = $newStock;
                $item->save();
            }

            ActivityLogger::record(
                'adjustment.deleted',
                "Adjustment #{$adjustment->id} ({$adjustment->reasonLabel()}) deleted. Stock restored.",
                $adjustment,
                ['lines' => ActivityLogger::lines($adjustment->lines()->with('item')->get(), 'unit_cost')]
            );

            $adjustment->delete();
        });
    }

    public function deleteTransfer(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $fromBatch = $transfer->po_no ? Batch::with('lines')->where('batch_no', $transfer->po_no)->first() : null;
            $batchQtyByItem = [];
            if ($fromBatch) {
                foreach ($fromBatch->lines as $bl) {
                    $batchQtyByItem[$bl->item_id] = ($batchQtyByItem[$bl->item_id] ?? 0) + (float) $bl->qty;
                }
            }

            foreach ($transfer->lines as $line) {
                // The line's rate is the invoice price, not purchase cost, so avg_cost stays as is
                $item = Item::lockForUpdate()->findOrFail($line->item_id);
                $qty = (float) $line->qty;
                $inBatch = $batchQtyByItem[$line->item_id] ?? 0;
                $toReturn = max(0, $qty - $inBatch);
                $batchQtyByItem[$line->item_id] = max(0, $inBatch - $qty);

                if ($toReturn > 0) {
                    $item->stock_qty = (float) $item->stock_qty + $toReturn;
                    $item->save();
                }
            }

            ActivityLogger::record(
                'transfer.deleted',
                "Invoice #{$transfer->invoice_no} to {$transfer->branch->name} deleted (".ActivityLogger::count($transfer->lines->count()).', '.money($transfer->total_value).'). Stock returned to warehouse.',
                $transfer,
                ['lines' => ActivityLogger::lines($transfer->lines()->with('item')->get(), 'unit_cost')]
            );

            $transfer->delete();
        });
    }

    /**
     * Corrects a saved invoice. Only the difference in qty per item touches
     * warehouse stock; lines keep the purchase cost they were sent at.
     */
    public function updateTransfer(Transfer $transfer, array $data, array $lines): Transfer
    {
        return DB::transaction(function () use ($transfer, $data, $lines) {
            $invoiceNo = (int) $data['invoice_no'];
            if (Transfer::where('invoice_no', $invoiceNo)->where('id', '!=', $transfer->id)->exists()) {
                throw ValidationException::withMessages(['invoice_no' => "Invoice #{$invoiceNo} already exists."]);
            }

            $old = $transfer->lines()->with('item')->get();
            $before = ActivityLogger::lines($old, 'unit_cost');
            $oldCost = $old->groupBy('item_id')->map(fn ($l) => (float) $l->first()->cost_price);

            $onInvoice = [];
            foreach ($old as $line) {
                $onInvoice[$line->item_id] = ($onInvoice[$line->item_id] ?? 0) + (float) $line->qty;
            }
            $change = $onInvoice;   // the old lines go back to stock...
            foreach ($lines as $line) {
                $change[$line['item_id']] = ($change[$line['item_id']] ?? 0) - (float) $line['qty'];   // ...and the new ones go out
            }

            $items = Item::lockForUpdate()->whereIn('id', array_keys($change))->get()->keyBy('id');
            $errors = [];
            foreach ($change as $id => $delta) {
                if ((float) $items[$id]->stock_qty + $delta < -0.0005) {
                    $available = (float) $items[$id]->stock_qty + ($onInvoice[$id] ?? 0);
                    $errors[] = "{$items[$id]->name}: at most ".qty($available)." {$items[$id]->unit} can be on this invoice (warehouse stock plus what it already had).";
                }
            }
            if ($errors) {
                throw ValidationException::withMessages(['lines' => $errors]);
            }

            foreach ($change as $id => $delta) {
                $items[$id]->stock_qty = (float) $items[$id]->stock_qty + $delta;
                $items[$id]->save();
            }

            $transfer->lines()->delete();
            $total = 0;
            foreach ($lines as $line) {
                $item = $items[$line['item_id']];
                $qty = (float) $line['qty'];
                $rate = (float) $line['rate'];
                $lineTotal = round($qty * $rate);
                $transfer->lines()->create([
                    'item_id' => $item->id,
                    'description' => self::optionalText($line['description'] ?? null),
                    'qty' => $qty,
                    'pieces' => self::optional($line['pieces'] ?? null),
                    'unit_cost' => $rate,
                    'cost_price' => $oldCost[$item->id] ?? (float) $item->avg_cost,
                    'total' => $lineTotal,
                ]);
                $total += $lineTotal;
            }

            $transfer->update([
                'invoice_no' => $invoiceNo,
                'branch_id' => $data['branch_id'],
                'transfer_date' => $data['transfer_date'],
                'due_days' => (int) ($data['due_days'] ?? 0),
                'po_no' => $data['po_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_value' => $total,
            ]);

            $after = ActivityLogger::lines($transfer->lines()->with('item')->get(), 'unit_cost');
            ActivityLogger::record(
                'transfer.updated',
                "Invoice #{$invoiceNo} to {$transfer->fresh()->branch->name} edited: ".money(array_sum(array_column($before, 'total'))).' → '.money($total).'. '.self::describeLineChanges($before, $after),
                $transfer,
                ['lines' => $after, 'before' => $before]
            );

            return $transfer->fresh();
        });
    }

    /**
     * Records items used from warehouse stock to prepare/make a batch.
     *
     * @param  array{batch_date: string, batch_no?: ?string, notes?: ?string}  $data
     * @param  array<int, array{item_id: int, qty: float|string}>  $lines
     */
    public function recordBatch(array $data, array $lines): Batch
    {
        return DB::transaction(function () use ($data, $lines) {
            $itemIds = array_unique(array_column($lines, 'item_id'));
            $items = Item::lockForUpdate()->whereIn('id', $itemIds)->get()->keyBy('id');

            $requested = [];
            $computedLines = [];

            foreach ($lines as $line) {
                $item = $items[$line['item_id']] ?? null;
                $enteredQty = (float) $line['qty'];
                $enteredUnit = strtolower(trim((string) ($line['unit'] ?? ($item?->unit ?? ''))));

                $isItemKg = $item && $item->isKg();
                $isUnitGram = in_array($enteredUnit, ['g', 'gm', 'gram', 'grams'], true);

                // If base item is kg and user entered in grams, convert to kg (divide by 1000)
                if ($isItemKg && $isUnitGram) {
                    $baseQty = round($enteredQty / 1000, 5);
                } else {
                    $baseQty = $enteredQty;
                }

                $requested[$line['item_id']] = ($requested[$line['item_id']] ?? 0) + $baseQty;
                $computedLines[] = [
                    'item' => $item,
                    'base_qty' => $baseQty,
                    'entered_qty' => $enteredQty,
                    'entered_unit' => $line['unit'] ?? ($item?->unit ?? null),
                ];
            }

            $errors = [];
            foreach ($requested as $itemId => $qty) {
                $item = $items[$itemId] ?? null;
                if (! $item) {
                    $errors[] = 'Item not found.';
                } elseif ((float) $item->stock_qty < $qty) {
                    $extraMsg = $item->isKg() ? ' ('.qty((float) $item->stock_qty * 1000).' g)' : '';
                    $errors[] = "{$item->name}: only ".qty($item->stock_qty)." {$item->unit}{$extraMsg} in stock.";
                }
            }
            if ($errors) {
                throw ValidationException::withMessages(['lines' => $errors]);
            }

            $batchNo = ! empty($data['batch_no']) ? trim((string) $data['batch_no']) : Batch::nextBatchNo();

            $batch = Batch::create([
                'batch_no' => $batchNo,
                'batch_date' => $data['batch_date'],
                'notes' => self::optionalText($data['notes'] ?? null),
                'total_cost' => 0,
            ]);

            $totalCost = 0;
            $hasUnitColumn = \Illuminate\Support\Facades\Schema::hasColumn('batch_items', 'unit');

            foreach ($computedLines as $cl) {
                $item = $cl['item'];
                $baseQty = $cl['base_qty'];
                $cost = (float) $item->avg_cost;
                $lineTotal = round($baseQty * $cost, 2);

                $lineAttributes = [
                    'item_id' => $item->id,
                    'qty' => $baseQty,
                    'unit_cost' => $cost,
                    'total' => $lineTotal,
                ];

                if ($hasUnitColumn) {
                    $lineAttributes['unit'] = $cl['entered_unit'];
                    $lineAttributes['entered_qty'] = $cl['entered_qty'];
                }

                $batch->lines()->create($lineAttributes);

                $item->stock_qty = (float) $item->stock_qty - $baseQty;
                $item->save();

                $totalCost += $lineTotal;
            }

            $batch->update(['total_cost' => $totalCost]);

            $batchLines = $batch->lines()->with('item')->get();
            ActivityLogger::record(
                'batch.created',
                "Batch {$batch->batch_no}: ".ActivityLogger::count($batchLines->count())." items used from stock, value ".money($totalCost),
                $batch,
                ['lines' => ActivityLogger::lines($batchLines, 'unit_cost')]
            );

            return $batch;
        });
    }

    public function deleteBatch(Batch $batch): void
    {
        DB::transaction(function () use ($batch) {
            if ($batch->transfer()->exists()) {
                $transfer = $batch->transfer;
                throw ValidationException::withMessages([
                    'batch' => "This batch was already sent to {$transfer->branch->name} (Transfer #{$transfer->invoice_no}). Delete that transfer first before deleting this batch.",
                ]);
            }

            foreach ($batch->lines as $line) {
                $item = Item::lockForUpdate()->findOrFail($line->item_id);
                $item->stock_qty = (float) $item->stock_qty + (float) $line->qty;
                $item->save();
            }

            ActivityLogger::record(
                'batch.deleted',
                "Batch {$batch->batch_no} deleted (".ActivityLogger::count($batch->lines->count()).', '.money($batch->total_cost).'). Stock returned to warehouse.',
                $batch,
                ['lines' => ActivityLogger::lines($batch->lines()->with('item')->get(), 'unit_cost')]
            );

            $batch->delete();
        });
    }

    private static function optional($value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private static function optionalText($value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : trim((string) $value);
    }

    /**
     * "Salt: rate 60 → 65; Honey added; Oil removed" for the activity log.
     */
    private static function describeLineChanges(array $before, array $after): string
    {
        $old = collect($before)->keyBy('name');
        $new = collect($after)->keyBy('name');
        $parts = [];

        foreach ($new as $name => $line) {
            if (! $old->has($name)) {
                $parts[] = "{$name} added";

                continue;
            }
            $was = $old[$name];
            $bits = [];
            if (abs($was['qty'] - $line['qty']) > 0.0005) {
                $bits[] = 'qty '.qty($was['qty']).' → '.qty($line['qty']);
            }
            if (abs($was['rate'] - $line['rate']) > 0.005) {
                $bits[] = 'rate '.qty($was['rate']).' → '.qty($line['rate']);
            }
            if ($bits) {
                $parts[] = "{$name}: ".implode(', ', $bits);
            }
        }
        foreach ($old->keys()->diff($new->keys()) as $name) {
            $parts[] = "{$name} removed";
        }

        return $parts ? implode('; ', $parts) : 'Details changed';
    }
}
