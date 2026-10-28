<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adjustment extends Model
{
    public const REASONS = [
        'wastage' => 'Wastage',
        'expired' => 'Expired',
        'damaged' => 'Damaged',
        'other' => 'Other',
        'count' => 'Stock count correction',
    ];

    protected $fillable = ['adjustment_date', 'reason', 'notes', 'total_value'];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'total_value' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AdjustmentItem::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }

    public function isCount(): bool
    {
        return $this->reason === 'count';
    }
}
