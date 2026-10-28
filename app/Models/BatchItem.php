<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchItem extends Model
{
    protected $fillable = ['batch_id', 'item_id', 'qty', 'unit', 'entered_qty', 'unit_cost', 'total'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'entered_qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function displayQty(): float
    {
        return $this->entered_qty !== null ? (float) $this->entered_qty : (float) $this->qty;
    }

    public function displayUnit(): string
    {
        return $this->unit ?: ($this->item?->unit ?? 'kg');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
