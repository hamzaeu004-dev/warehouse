<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchPayment;
use App\Models\Transfer;
use Illuminate\Support\Collection;

/**
 * Payments are recorded per branch, not per invoice, so they are applied to
 * that branch's invoices oldest first. That gives every invoice a status:
 * paid, partly paid, or payment pending (overdue once its due date passes).
 */
class InvoiceStatus
{
    public const PAID = 'paid';

    public const PARTIAL = 'partial';

    public const PENDING = 'pending';

    /**
     * @param  iterable<int>|null  $branchIds  null = all branches
     * @return Collection<int, object{status: string, paid: float, due: float, overdue: bool, days_overdue: int}> keyed by transfer id
     */
    public static function forBranches(?iterable $branchIds = null): Collection
    {
        $ids = $branchIds === null ? null : collect($branchIds)->unique()->values()->all();

        $paidByBranch = BranchPayment::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('branch_id', $ids))
            ->groupBy('branch_id')
            ->selectRaw('branch_id, SUM(amount) as paid')
            ->pluck('paid', 'branch_id');

        $result = collect();
        $today = now()->startOfDay();

        Transfer::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('branch_id', $ids))
            ->orderBy('transfer_date')->orderBy('id')
            ->get(['id', 'branch_id', 'transfer_date', 'due_days', 'total_value'])
            ->groupBy('branch_id')
            ->each(function ($invoices, $branchId) use ($paidByBranch, $result, $today) {
                $money = (float) ($paidByBranch[$branchId] ?? 0);

                foreach ($invoices as $invoice) {
                    $amount = (float) $invoice->total_value;
                    $paid = round(min($amount, max($money, 0)), 2);
                    $money -= $paid;
                    $due = round($amount - $paid, 2);

                    $status = $due <= 0.005 ? self::PAID : ($paid > 0 ? self::PARTIAL : self::PENDING);
                    $dueDate = $invoice->dueDate()->startOfDay();
                    $overdue = $status !== self::PAID && $dueDate->lt($today);

                    $result[$invoice->id] = (object) [
                        'status' => $status,
                        'paid' => $paid,
                        'due' => max($due, 0),
                        'overdue' => $overdue,
                        'days_overdue' => $overdue ? (int) $dueDate->diffInDays($today) : 0,
                    ];
                }
            });

        return $result;
    }

    public static function forInvoice(Transfer $transfer): object
    {
        return self::forBranches([$transfer->branch_id])[$transfer->id];
    }

    /**
     * Branches that still owe money, with how many invoices are unpaid and the oldest one.
     */
    public static function pendingByBranch(): Collection
    {
        $statuses = self::forBranches();
        $transfers = Transfer::whereIn('id', $statuses->keys())->get(['id', 'branch_id', 'transfer_date'])->keyBy('id');

        return Branch::orderBy('id')->get()
            ->map(function (Branch $branch) use ($statuses, $transfers) {
                $unpaid = $statuses->filter(fn ($s, $id) => $transfers[$id]->branch_id === $branch->id && $s->status !== self::PAID);

                return (object) [
                    'branch' => $branch,
                    'due' => round($unpaid->sum('due'), 2),
                    'invoices' => $unpaid->count(),
                    'overdue' => $unpaid->filter(fn ($s) => $s->overdue)->count(),
                    'oldest' => $unpaid->keys()->map(fn ($id) => $transfers[$id]->transfer_date)->min(),
                ];
            })
            ->filter(fn ($row) => $row->due > 0)
            ->sortByDesc('due')
            ->values();
    }

    public static function label(object $status): string
    {
        return match (true) {
            $status->status === self::PAID => 'Paid',
            $status->overdue => 'Overdue',
            $status->status === self::PARTIAL => 'Partly paid',
            default => 'Payment pending',
        };
    }

    public static function badgeClass(object $status): string
    {
        return match (true) {
            $status->status === self::PAID => 'badge-green',
            $status->overdue => 'badge-red',
            default => 'badge-amber',
        };
    }
}
