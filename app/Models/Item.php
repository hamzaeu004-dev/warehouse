<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = ['category_id', 'name', 'unit', 'stock_qty', 'avg_cost', 'last_price', 'sale_rate', 'min_stock', 'is_active'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'category_id' => 'integer',
            'stock_qty' => 'decimal:3',
            'avg_cost' => 'decimal:2',
            'last_price' => 'decimal:2',
            'sale_rate' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function adjustmentItems(): HasMany
    {
        return $this->hasMany(AdjustmentItem::class);
    }

    public function batchItems(): HasMany
    {
        return $this->hasMany(BatchItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->min_stock !== null && (float) $this->stock_qty <= (float) $this->min_stock;
    }

    public function isKg(): bool
    {
        return in_array(strtolower(trim((string) $this->unit)), ['kg', 'kgs', 'kilogram', 'kilograms'], true);
    }
}
