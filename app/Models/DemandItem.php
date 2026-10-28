<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandItem extends Model
{
    protected $fillable = [
        'demand_id',
        'item_id',
        'qty',
        'unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
        ];
    }

    public function displayUnit(): string
    {
        return $this->unit ?: ($this->item?->unit ?? 'kg');
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
