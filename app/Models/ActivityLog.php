<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    // Logs are temporary: anything older than this is deleted automatically
    public const KEEP_DAYS = 7;

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

    /**
     * Deletes logs older than KEEP_DAYS. Runs when a log is written or the
     * log page opens, so it works on hosting without a cron job.
     */
    public static function pruneOld(): int
    {
        return static::where('created_at', '<', now()->subDays(self::KEEP_DAYS)->startOfDay())->delete();
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
