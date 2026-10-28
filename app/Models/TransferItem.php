<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferItem extends Model
{
    protected $fillable = ['transfer_id', 'item_id', 'description', 'qty', 'pieces', 'unit_cost', 'cost_price', 'total'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'pieces' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Invoice columns: weight goes under "Kgs" when the item is counted in kg,
     * otherwise the stock quantity is the "Qty".
     */
    public function invoiceKgs(): ?float
    {
        return $this->item->unit === 'kg' ? (float) $this->qty : null;
    }

    public function invoiceQty(): ?float
    {
        if ($this->pieces !== null) {
            return (float) $this->pieces;
        }

        return $this->item->unit === 'kg' ? null : (float) $this->qty;
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
