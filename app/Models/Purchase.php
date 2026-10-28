<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = ['purchase_date', 'store_name', 'notes', 'total'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
