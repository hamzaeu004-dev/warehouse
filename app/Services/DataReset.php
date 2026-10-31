<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\Branch;
use App\Models\BranchPayment;
use App\Models\Category;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Support\Facades\DB;

/**
 * Clears test data so the warehouse can start for real. The manager's login
 * is never touched. Children are deleted before parents so foreign keys hold.
 */
class DataReset
{
    /**
     * Batches, purchases, invoices, adjustments, payments and the activity log go;
     * items, categories and branches stay, with stock set back to zero.
     */
    public static function transactions(): void
    {
        DB::transaction(function () {
            BatchItem::query()->delete();
            Batch::query()->delete();
            TransferItem::query()->delete();
            Transfer::query()->delete();
            PurchaseItem::query()->delete();
            Purchase::query()->delete();
            AdjustmentItem::query()->delete();
            Adjustment::query()->delete();
            BranchPayment::query()->delete();
            ActivityLog::query()->delete();

            Item::query()->update(['stock_qty' => 0, 'avg_cost' => 0, 'last_price' => null]);
        });

        ActivityLogger::record('account.reset', 'Test data cleared: batches, purchases, invoices, adjustments and payments deleted, stock set to 0. Items, categories and branches kept.');
    }

    /**
     * Everything except the login: also items, categories and branches,
     * so they can be entered by hand from scratch.
     */
    public static function everything(): void
    {
        self::transactions();

        DB::transaction(function () {
            Item::query()->delete();
            Category::query()->delete();
            Branch::query()->delete();
            ActivityLog::query()->delete();
        });

        ActivityLogger::record('account.reset', 'All data cleared: items, categories, branches and all transactions deleted. Starting fresh.');
    }
}
