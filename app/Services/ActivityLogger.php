<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * @param  string  $action  group.event, e.g. "purchase.created"
     * @param  string  $description  sentence shown in the log, written for a person to read
     * @param  Model|null  $subject  record this is about; linked from the log while it exists
     */
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = []): ActivityLog
    {
        ActivityLog::pruneOld();

        return ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? strtolower(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_strimwidth($description, 0, 500, '…'),
            'properties' => $properties ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * Item lines in the shape the log page shows under "Show items".
     */
    public static function lines(iterable $lines, string $rateField): array
    {
        $out = [];
        foreach ($lines as $line) {
            $unit = ! empty($line->unit) ? $line->unit : $line->item->unit;
            $qty = (float) ($line->entered_qty ?? $line->qty ?? $line->qty_change);
            $out[] = [
                'name' => $line->item->name,
                'qty' => $qty,
                'unit' => $unit,
                'rate' => (float) $line->{$rateField},
                'total' => (float) $line->total,
            ];
        }

        return $out;
    }

    public static function count(int $n, string $word = 'item'): string
    {
        return $n.' '.($n === 1 ? $word : $word.'s');
    }
}
