<?php

namespace App\Services;

use App\Models\AdjustmentItem;
use App\Models\Item;
use App\Models\TransferItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Stock arrives once a week, so the shopping list is: what a week uses,
 * plus the safety minimum, minus what is already in the warehouse.
 * "Use" = sent to branches + lost to wastage/expiry/damage.
 */
class OrderPlanner
{
    public const BASES = [
        'last_week' => 'Last week',
        'this_week' => 'This week so far',
        'avg4' => '4-week avg',
    ];

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int} from, to, number of weeks
     */
    public static function window(string $basis): array
    {
        // Handy at the end of the week, when the list is made for the next day's shopping
        if ($basis === 'this_week') {
            [$from] = ProfitReport::range('week', now());

            return [$from, now(), 1];
        }

        [$lastFrom, $lastTo] = ProfitReport::range('week', now()->subWeek());
        $weeks = $basis === 'avg4' ? 4 : 1;

        return [$lastFrom->copy()->subWeeks($weeks - 1), $lastTo, $weeks];
    }

    public static function suggestions(string $basis = 'last_week'): Collection
    {
        [$from, $to, $weeks] = self::window($basis);
        $dates = [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()];

        $sent = TransferItem::join('transfers', 'transfers.id', '=', 'transfer_items.transfer_id')
            ->whereBetween('transfers.transfer_date', $dates)
            ->groupBy('transfer_items.item_id')
            ->selectRaw('transfer_items.item_id, SUM(transfer_items.qty) as qty')
            ->pluck('qty', 'item_id');

        $lost = AdjustmentItem::join('adjustments', 'adjustments.id', '=', 'adjustment_items.adjustment_id')
            ->whereBetween('adjustments.adjustment_date', $dates)
            ->where('adjustments.reason', '!=', 'count')
            ->where('adjustment_items.qty_change', '<', 0)
            ->groupBy('adjustment_items.item_id')
            ->selectRaw('adjustment_items.item_id, -SUM(adjustment_items.qty_change) as qty')
            ->pluck('qty', 'item_id');

        return Item::with('category')->where('is_active', true)->get()
            ->map(function (Item $item) use ($sent, $lost, $weeks) {
                $weekly = round(((float) ($sent[$item->id] ?? 0) + (float) ($lost[$item->id] ?? 0)) / $weeks, 3);
                $minimum = (float) ($item->min_stock ?? 0);
                $stock = (float) $item->stock_qty;
                $need = max(0, $weekly + $minimum - $stock);
                // Whole units for things counted in pieces, 0.5 steps for weights/volumes
                $need = in_array($item->unit, ['kg', 'litre', 'gram', 'ml'], true) ? ceil($need * 2) / 2 : ceil($need);

                return (object) [
                    'item' => $item,
                    'weekly_use' => $weekly,
                    'stock' => $stock,
                    'minimum' => $minimum,
                    'suggested' => $need,
                    'price' => $item->last_price !== null ? (float) $item->last_price : (float) $item->avg_cost,
                ];
            })
            ->filter(fn ($row) => $row->weekly_use > 0 || $row->minimum > 0)
            ->sortBy([fn ($a, $b) => $a->item->category->sort_order <=> $b->item->category->sort_order, fn ($a, $b) => strcmp($a->item->name, $b->item->name)])
            ->values();
    }
}
