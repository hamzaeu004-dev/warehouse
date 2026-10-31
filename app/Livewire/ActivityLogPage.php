<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Adjustment;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Transfer;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Activity Log')]
class ActivityLogPage extends Component
{
    #[Url(except: '')]
    public string $group = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $date = '';

    public int $perPage = 50;

    public function showMore(): void
    {
        $this->perPage += 50;
    }

    public function clearFilters(): void
    {
        $this->reset('date', 'search');
    }

    public function updated(): void
    {
        $this->perPage = 50;
    }

    public function mount(): void
    {
        ActivityLog::pruneOld();
    }

    public function render()
    {
        $query = ActivityLog::query()
            ->when($this->group !== '', fn ($q) => $q->where('action', 'like', $this->group.'.%'))
            ->when($this->search !== '', fn ($q) => $q->where('description', 'like', '%'.$this->search.'%'))
            ->when($this->date !== '', fn ($q) => $q->whereDate('created_at', $this->date));

        $logs = (clone $query)->with('user')->latest('created_at')->latest('id')->limit($this->perPage)->get();

        return view('livewire.activity-log', [
            'groups' => ActivityLog::GROUPS,
            'days' => $logs->groupBy(fn ($log) => $log->created_at->toDateString()),
            'shown' => $logs->count(),
            'total' => (clone $query)->count(),
            'links' => $this->linksFor($logs),
        ]);
    }

    /**
     * Link each log entry to its record, but only while that record still exists.
     *
     * @return array<int, string> log id => url
     */
    private function linksFor($logs): array
    {
        $routes = [
            'purchase' => [Purchase::class, 'purchases.show'],
            'batch' => [Batch::class, 'batches.show'],
            'transfer' => [Transfer::class, 'transfers.show'],
            'adjustment' => [Adjustment::class, 'adjustments.show'],
            'item' => [Item::class, 'items.show'],
            'branch' => [Branch::class, null],
        ];

        $existing = [];
        foreach ($logs->whereNotNull('subject_type')->groupBy('subject_type') as $type => $group) {
            if (isset($routes[$type])) {
                $existing[$type] = $routes[$type][0]::whereIn('id', $group->pluck('subject_id'))->pluck('id')->flip();
            }
        }

        $links = [];
        foreach ($logs as $log) {
            $type = $log->subject_type;
            if ($type && isset($existing[$type][$log->subject_id])) {
                $links[$log->id] = $routes[$type][1] ? route($routes[$type][1], $log->subject_id) : route('branches');
            }
        }

        return $links;
    }
}
