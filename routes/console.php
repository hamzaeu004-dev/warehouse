<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:clean-test-data', function () {
    \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
    \App\Models\BatchItem::truncate();
    \App\Models\Batch::truncate();
    \App\Models\TransferItem::truncate();
    \App\Models\Transfer::truncate();
    \App\Models\PurchaseItem::truncate();
    \App\Models\Purchase::truncate();
    \App\Models\AdjustmentItem::truncate();
    \App\Models\Adjustment::truncate();
    \App\Models\BranchPayment::truncate();
    \App\Models\ActivityLog::truncate();
    \App\Models\Item::query()->update([
        'stock_qty' => 0,
        'avg_cost' => 0,
        'last_price' => null,
    ]);
    \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

    $this->info('Testing transactions cleared and item stocks reset to 0.');
})->purpose('Clean test transactions and reset stock');
