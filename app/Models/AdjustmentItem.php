<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdjustmentItem extends Model
{
    protected $fillable = ['adjustment_id', 'item_id', 'qty_change', 'system_qty', 'counted_qty', 'unit_cost', 'total'];

    protected function casts(): array
    {
        return [
            'qty_change' => 'decimal:3',
            'system_qty' => 'decimal:3',
            'counted_qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
