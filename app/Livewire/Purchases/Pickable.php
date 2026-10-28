<?php

namespace App\Livewire\Purchases;

use App\Models\Item;
use App\Models\TransferItem;

/**
 * Item data sent to the browser for the item picker on the purchase,
 * invoice and adjustment screens.
 */
class Pickable
{
    /**
     * Active items, plus any hidden ones listed in $alsoInclude (items already
     * on a purchase/invoice being edited).
     */
    public static function all(array $alsoInclude = []): array
    {
        return Item::with('category')
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $alsoInclude))
            ->addSelect(['last_transfer_rate' => TransferItem::select('unit_cost')
                ->whereColumn('item_id', 'items.id')
                ->latest('id')
                ->limit(1),
            ])
            ->orderBy('name')->get()
            ->map(fn (Item $item) => self::item($item))
            ->all();
    }

    public static function item(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'unit' => $item->unit,
            'category' => $item->category->name,
            'last_price' => $item->last_price !== null ? (float) $item->last_price : null,
            'sale_rate' => $item->sale_rate !== null ? (float) $item->sale_rate : null,
            'avg_cost' => (float) $item->avg_cost,
            'last_transfer_rate' => $item->last_transfer_rate !== null ? (float) $item->last_transfer_rate : null,
            'stock' => (float) $item->stock_qty,
        ];
    }
}
