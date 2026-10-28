<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = ['batch_no', 'batch_date', 'notes', 'total_cost'];

    protected function casts(): array
    {
        return [
            'batch_date' => 'date',
            'total_cost' => 'decimal:2',
        ];
    }

    public static function nextBatchNo(): string
    {
        $max = (int) static::max('id');
        return 'B-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BatchItem::class);
    }

    public function transfer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Transfer::class, 'po_no', 'batch_no');
    }
}
