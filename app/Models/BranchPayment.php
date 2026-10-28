<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchPayment extends Model
{
    public const METHODS = [
        'cash' => 'Cash',
        'bank' => 'Bank transfer',
        'cheque' => 'Cheque',
        'online' => 'Easypaisa / JazzCash',
    ];

    protected $fillable = ['branch_id', 'payment_date', 'amount', 'method', 'reference', 'notes'];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst($this->method);
    }
}
