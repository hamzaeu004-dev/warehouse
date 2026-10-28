<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Adjustment;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Transfer;
use App\Services\OrderPlanner;
use App\Services\ProfitReport;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Home')]
class Dashboard extends Component
{
    public function render()
    {
        $weekStart = ProfitReport::range('week', now())[0];
        $monthStart = now()->startOfMonth();

        $lowStockQuery = Item::where('is_active', true)->whereNotNull('min_stock')->whereColumn('stock_qty', '<=', 'min_stock');
        $branchesQuery = Branch::orderBy('id')->withSum(['transfers as month_total' => fn ($q) => $q->where('transfer_date', '>=', $monthStart)], 'total_value');

        return view('livewire.dashboard', [
            'stockValue' => (float) Item::sum(DB::raw('stock_qty * avg_cost')),
            'itemsInStock' => Item::where('is_active', true)->where('stock_qty', '>', 0)->count(),
            'itemsTotal' => Item::where('is_active', true)->count(),
            'weekPurchases' => (float) Purchase::where('purchase_date', '>=', $weekStart)->sum('total'),
            'monthPurchases' => (float) Purchase::where('purchase_date', '>=', $monthStart)->sum('total'),
            'monthLoss' => (float) Adjustment::where('adjustment_date', '>=', $monthStart)->where('total_value', '<', 0)->sum('total_value'),
            'monthDispatched' => (float) Transfer::where('transfer_date', '>=', $monthStart)->sum('total_value'),
            'weekDispatched' => (float) Transfer::where('transfer_date', '>=', $weekStart)->sum('total_value'),
            'branchTotals' => (clone $branchesQuery)->limit(3)->get(),
            'totalBranchesCount' => $branchesQuery->count(),
            'lowStock' => (clone $lowStockQuery)->orderBy('name')->limit(3)->get(),
            'totalLowStockCount' => $lowStockQuery->count(),
            'recentPurchases' => Purchase::withCount('lines')->latest('purchase_date')->latest('id')->limit(3)->get(),
            'totalPurchasesCount' => Purchase::count(),
            'orderCount' => OrderPlanner::suggestions()->where('suggested', '>', 0)->count(),
            'weekProfit' => ProfitReport::totals(...ProfitReport::range('week', now())),
            'recentActivity' => ActivityLog::latest('created_at')->latest('id')->limit(3)->get(),
            'totalActivityCount' => ActivityLog::count(),
            'recentTransfers' => Transfer::with('branch')->withCount('lines')->latest('transfer_date')->latest('id')->limit(3)->get(),
            'totalTransfersCount' => Transfer::count(),
            'recentBatches' => Batch::withCount('lines')->latest('batch_date')->latest('id')->limit(3)->get(),
            'totalBatchesCount' => Batch::count(),
        ]);
    }
}
