<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    // Filter groups on the Activity Log page, keyed by the action prefix
    public const GROUPS = [
        'purchase' => 'Purchases',
        'batch' => 'Batches',
        'transfer' => 'Transfers',
        'adjustment' => 'Adjustments',
        'item' => 'Items',
        'category' => 'Categories',
        'payment' => 'Payments',
        'branch' => 'Branches',
        'account' => 'Login & account',
    ];

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'description', 'properties', 'ip_address'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): string
    {
        return explode('.', $this->action)[0];
    }

    public function isDeletion(): bool
    {
        return str_ends_with($this->action, '.deleted');
    }

    public function isWarning(): bool
    {
        return $this->action === 'account.login_failed';
    }
}
