<?php

namespace App\Services;

use App\Models\Adjustment;
use App\Models\Branch;
use App\Models\BranchPayment;
use App\Models\Purchase;
use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Money figures for a date range:
 *  - investment: what was spent buying stock (purchases)
 *  - sales: what branches were invoiced
 *  - cost: what the invoiced stock cost the warehouse (avg purchase cost when sent)
 *  - received: what branches actually paid (the main profit basis)
 *  - stock loss/gain from adjustments (wastage, expired, counts)
 *  - profit on money received = received - cost + adjustments
 *  - profit on invoices = sales - cost + adjustments (shown as the alternative)
 */
class ProfitReport
{
    public const PERIODS = ['day', 'week', 'month', 'year'];

    public static function weekStartsOn(): int
    {
        $days = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];

        return $days[strtolower((string) config('inventory.week_starts_on'))] ?? Carbon::MONDAY;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(string $period, Carbon $anchor): array
    {
        $date = $anchor->copy();

        return match ($period) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'week' => [$date->copy()->startOfWeek(self::weekStartsOn()), $date->copy()->startOfWeek(self::weekStartsOn())->addDays(6)->endOfDay()],
            'year' => [$date->copy()->startOfYear(), $date->copy()->endOfYear()],
            default => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
        };
    }

    public static function shift(string $period, Carbon $anchor, int $steps): Carbon
    {
        return match ($period) {
            'day' => $anchor->copy()->addDays($steps),
            'week' => $anchor->copy()->addWeeks($steps),
            'year' => $anchor->copy()->addYearsNoOverflow($steps),
            default => $anchor->copy()->addMonthsNoOverflow($steps),
        };
    }

    public static function label(string $period, Carbon $from, Carbon $to, bool $short = false): string
    {
        return match ($period) {
            'day' => $short ? $from->format('D d') : $from->format('l, d M Y'),
            'week' => $short ? $from->format('d M') : $from->format('d M').' – '.$to->format('d M Y'),
            'year' => $from->format('Y'),
            default => $short ? $from->format('M') : $from->format('F Y'),
        };
    }

    public static function totals(Carbon $from, Carbon $to): array
    {
        // Full days, so a one-day range works whether the column holds a date or a datetime
        $dates = [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()];

        $investment = (float) Purchase::whereBetween('purchase_date', $dates)->sum('total');
        $sales = (float) Transfer::whereBetween('transfer_date', $dates)->sum('total_value');
        $cost = (float) TransferItem::join('transfers', 'transfers.id', '=', 'transfer_items.transfer_id')
            ->whereBetween('transfers.transfer_date', $dates)
            ->selectRaw('COALESCE(SUM(transfer_items.qty * transfer_items.cost_price), 0) as cost')
            ->value('cost');
        $adjustments = (float) Adjustment::whereBetween('adjustment_date', $dates)->sum('total_value');
        $received = (float) BranchPayment::whereBetween('payment_date', $dates)->sum('amount');

        $gross = round($sales - $cost, 2);
        $cashGross = round($received - $cost, 2);

        return [
            'investment' => $investment,
            'sales' => $sales,
            'cost' => round($cost, 2),
            'adjustments' => $adjustments,
            // On invoices: what branches were billed minus what that stock cost
            'gross_profit' => $gross,
            'net_profit' => round($gross + $adjustments, 2),
            'margin' => $sales > 0 ? round($gross / $sales * 100, 1) : null,
            // On money received: what branches actually paid minus what the stock sent cost
            // (stock worth 800 sent, 1,000 paid = 200 profit)
            'received' => $received,
            'cash_gross' => $cashGross,
            'cash_profit' => round($cashGross + $adjustments, 2),
            'cash_margin' => $received > 0 ? round($cashGross / $received * 100, 1) : null,
        ];
    }

    /**
     * The last $count periods ending with the one containing $anchor, oldest first.
     */
    public static function series(string $period, Carbon $anchor, int $count): array
    {
        $rows = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            [$from, $to] = self::range($period, self::shift($period, $anchor, -$i));
            $rows[] = ['from' => $from, 'to' => $to, 'label' => self::label($period, $from, $to, true), 'long' => self::label($period, $from, $to)] + self::totals($from, $to);
        }

        return $rows;
    }

    /**
     * Per branch for the range: invoiced (sales), stock cost and money received.
     * Branches with no activity in the range are left out.
     */
    public static function byBranch(Carbon $from, Carbon $to)
    {
        $dates = [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()];

        $sent = TransferItem::join('transfers', 'transfers.id', '=', 'transfer_items.transfer_id')
            ->whereBetween('transfers.transfer_date', $dates)
            ->groupBy('transfers.branch_id')
            ->get(['transfers.branch_id', DB::raw('SUM(transfer_items.total) as sales'), DB::raw('SUM(transfer_items.qty * transfer_items.cost_price) as cost')])
            ->keyBy('branch_id');
        $paid = BranchPayment::whereBetween('payment_date', $dates)
            ->groupBy('branch_id')
            ->selectRaw('branch_id, SUM(amount) as received')
            ->pluck('received', 'branch_id');

        return Branch::orderBy('id')->get()
            ->map(fn (Branch $b) => (object) [
                'id' => $b->id,
                'name' => $b->name,
                'sales' => (float) ($sent[$b->id]->sales ?? 0),
                'cost' => (float) ($sent[$b->id]->cost ?? 0),
                'received' => (float) ($paid[$b->id] ?? 0),
            ])
            ->filter(fn ($b) => $b->sales > 0 || $b->received > 0)
            ->sortByDesc(fn ($b) => max($b->sales, $b->received))
            ->values();
    }

    /**
     * Profit per item for the range, most profitable first.
     */
    public static function byItem(Carbon $from, Carbon $to, int $limit = 10)
    {
        return TransferItem::join('transfers', 'transfers.id', '=', 'transfer_items.transfer_id')
            ->join('items', 'items.id', '=', 'transfer_items.item_id')
            ->whereBetween('transfers.transfer_date', [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->groupBy('items.id', 'items.name', 'items.unit')
            ->orderByRaw('SUM(transfer_items.total) - SUM(transfer_items.qty * transfer_items.cost_price) DESC')
            ->limit($limit)
            ->get([
                'items.id', 'items.name', 'items.unit',
                DB::raw('SUM(transfer_items.qty) as qty'),
                DB::raw('SUM(transfer_items.total) as sales'),
                DB::raw('SUM(transfer_items.qty * transfer_items.cost_price) as cost'),
            ]);
    }
}
