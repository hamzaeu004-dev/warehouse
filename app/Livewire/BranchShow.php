<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\TransferItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class BranchShow extends Component
{
    public const PERIODS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'all' => 'All time',
    ];

    public Branch $branch;

    #[Url(except: 'this_month')]
    public string $period = 'this_month';

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function range(): array
    {
        return match ($this->period) {
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()],
            'all' => [null, null],
            default => [now()->startOfMonth(), now()],
        };
    }

    public function render()
    {
        [$from, $to] = $this->range();

        $transfers = $this->branch->transfers()
            ->when($from, fn ($q) => $q->whereBetween('transfer_date', [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()]))
            ->withCount('lines');

        // One row per item: what was sent to this branch and how much
        $items = TransferItem::query()
            ->join('transfers', 'transfers.id', '=', 'transfer_items.transfer_id')
            ->join('items', 'items.id', '=', 'transfer_items.item_id')
            ->where('transfers.branch_id', $this->branch->id)
            ->when($from, fn ($q) => $q->whereBetween('transfers.transfer_date', [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()]))
            ->groupBy('items.id', 'items.name', 'items.unit')
            ->orderByDesc(DB::raw('SUM(transfer_items.total)'))
            ->get([
                'items.id', 'items.name', 'items.unit',
                DB::raw('SUM(transfer_items.qty) as qty'),
                DB::raw('SUM(transfer_items.total) as total'),
            ]);

        return view('livewire.branch-show', [
            'periods' => self::PERIODS,
            'from' => $from,
            'to' => $to,
            'totalValue' => (float) (clone $transfers)->sum('total_value'),
            'transferCount' => (clone $transfers)->count(),
            'totalValueAllTime' => (float) $this->branch->transfers()->sum('total_value'),
            'totalTransfersAllTime' => (int) $this->branch->transfers()->count(),
            'items' => $items,
            'recentTransfers' => (clone $transfers)->latest('transfer_date')->latest('id')->limit(15)->get(),
        ])->title($this->branch->name);
    }
}
