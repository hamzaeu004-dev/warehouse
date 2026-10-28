<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Demand extends Model
{
    protected $fillable = [
        'demand_no',
        'branch_id',
        'demand_date',
        'status',
        'notes',
        'batch_id',
        'transfer_id',
    ];

    protected function casts(): array
    {
        return [
            'demand_date' => 'date',
        ];
    }

    public static function nextDemandNo(): string
    {
        $max = (int) static::max('id');
        return 'D-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DemandItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInPreparation(): bool
    {
        return $this->status === 'in_preparation';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'in_preparation' => 'In Preparation',
            'completed' => 'Transferred',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-amber-100 text-amber-800 ring-1 ring-amber-300',
            'in_preparation' => 'bg-sky-100 text-sky-800 ring-1 ring-sky-300',
            'completed' => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300',
            'cancelled' => 'bg-slate-100 text-slate-600 ring-1 ring-slate-300',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}
