<?php

namespace App\Services;

use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Models\Batch;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Transfer;
use App\Models\TransferItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The report the owner asks for: how much was bought, how much went to the
 * branches, and how much was lost to wastage, for any date range.
 */
class OwnerReport
{
    public const PERIODS = [
        'this_week' => 'This week',
        'last_week' => 'Last week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'custom' => 'Custom',
    ];

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(string $period, ?string $from = null, ?string $to = null): array
    {
        return match ($period) {
            'last_week' => ProfitReport::range('week', now()->subWeek()),
            'this_month' => [now()->startOfMonth(), now()->endOfDay()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfDay()],
            'custom' => self::customRange($from, $to),
            default => [ProfitReport::range('week', now())[0], now()->endOfDay()],
        };
    }

    private static function customRange(?string $from, ?string $to): array
    {
        try {
            $start = Carbon::parse($from ?: now()->startOfMonth())->startOfDay();
            $end = Carbon::parse($to ?: now())->endOfDay();
        } catch (\Throwable) {
            return [now()->startOfMonth(), now()->endOfDay()];
        }

        return $start->lte($end) ? [$start, $end] : [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
    }

    public static function build(Carbon $from, Carbon $to): array
    {
        $dates = [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()];

        // 1. Purchases
        $purchases = Purchase::withCount('lines')->whereBetween('purchase_date', $dates)->orderBy('purchase_date')->orderBy('id')->get();
        $purchasedItems = PurchaseItem::join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('items', 'items.id', '=', 'purchase_items.item_id')
            ->whereBetween('purchases.purchase_date', $dates)
            ->groupBy('items.id', 'items.name', 'items.unit')
            ->orderByDesc(DB::raw('SUM(purchase_items.total)'))
            ->get(['items.name', 'items.unit', DB::raw('SUM(purchase_items.qty) as qty'), DB::raw('SUM(purchase_items.total) as amount')]);

        // 2. Delivered to branches (invoice amounts, and what that stock cost)
        $transfers = Transfer::with('branch')->withCount('lines')->whereBetween('transfer_date', $dates)->get();
        $deliveredByBranch = $transfers->groupBy('branch_id')->map(fn ($group) => (object) [
            'name' => $group->first()->branch->name,
            'invoices' => $group->count(),
            'amount' => (float) $group->sum('total_value'),
        ])->sortByDesc('amount')->values();
        $deliveredItems = TransferItem::join('transfers', 'transfers.id', '=', 'transfer_items.transfer_id')
            ->join('items', 'items.id', '=', 'transfer_items.item_id')
            ->whereBetween('transfers.transfer_date', $dates)
            ->groupBy('items.id', 'items.name', 'items.unit')
            ->orderByDesc(DB::raw('SUM(transfer_items.total)'))
            ->get([
                'items.name', 'items.unit',
                DB::raw('SUM(transfer_items.qty) as qty'),
                DB::raw('SUM(transfer_items.total) as amount'),
                DB::raw('SUM(transfer_items.qty * transfer_items.cost_price) as cost'),
            ]);

        // 3. Wastage: stock thrown away, expired or damaged (counts are listed separately)
        $wastage = AdjustmentItem::join('adjustments', 'adjustments.id', '=', 'adjustment_items.adjustment_id')
            ->join('items', 'items.id', '=', 'adjustment_items.item_id')
            ->whereBetween('adjustments.adjustment_date', $dates)
            ->where('adjustments.reason', '!=', 'count')
            ->groupBy('items.id', 'items.name', 'items.unit', 'adjustments.reason')
            ->orderBy(DB::raw('SUM(adjustment_items.total)'))
            ->get([
                'items.name', 'items.unit', 'adjustments.reason',
                DB::raw('-SUM(adjustment_items.qty_change) as qty'),
                DB::raw('-SUM(adjustment_items.total) as value'),
            ])
            ->each(fn ($row) => $row->reason_label = Adjustment::REASONS[$row->reason] ?? ucfirst($row->reason));
        $countDifference = (float) Adjustment::whereBetween('adjustment_date', $dates)->where('reason', 'count')->sum('total_value');

        // Prepared in batches but not sent to a branch yet
        $unsentBatches = Batch::whereBetween('batch_date', $dates)->whereDoesntHave('transfer')->get();

        $purchaseTotal = (float) $purchases->sum('total');
        $deliveredTotal = (float) $transfers->sum('total_value');
        $deliveredCost = (float) $deliveredItems->sum('cost');
        $wastageTotal = (float) $wastage->sum('value');

        return [
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
            'summary' => [
                'purchased' => $purchaseTotal,
                'purchase_count' => $purchases->count(),
                'delivered' => $deliveredTotal,
                'delivered_cost' => $deliveredCost,
                'invoice_count' => $transfers->count(),
                'wastage' => $wastageTotal,
                'count_difference' => $countDifference,
                'unsent_batches' => (float) $unsentBatches->sum('total_cost'),
                'unsent_batch_count' => $unsentBatches->count(),
                'stock_value_now' => (float) Item::sum(DB::raw('stock_qty * avg_cost')),
            ],
            'purchases' => $purchases,
            'purchasedItems' => $purchasedItems,
            'deliveredByBranch' => $deliveredByBranch,
            'deliveredItems' => $deliveredItems,
            'wastage' => $wastage,
        ];
    }

    /**
     * Only the letters actually used go into the file, which keeps it small
     * enough to send on WhatsApp.
     */
    public static function pdf(Carbon $from, Carbon $to): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('reports.owner-pdf', self::build($from, $to))
            ->setPaper('a4')
            ->setOption(['isFontSubsettingEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
    }

    public static function fileName(Carbon $from, Carbon $to): string
    {
        return 'Warehouse-report_'.$from->format('d-M-Y').'_to_'.$to->format('d-M-Y').'.pdf';
    }
}
