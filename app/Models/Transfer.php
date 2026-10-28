<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    protected $fillable = ['invoice_no', 'branch_id', 'transfer_date', 'due_days', 'po_no', 'notes', 'total_value'];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'total_value' => 'decimal:2',
        ];
    }

    public static function nextInvoiceNo(): int
    {
        return ((int) static::max('invoice_no')) + 1;
    }

    public function dueDate(): \Illuminate\Support\Carbon
    {
        return $this->transfer_date->copy()->addDays($this->due_days);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'po_no', 'batch_no');
    }
}
