<?php

namespace App\Livewire\Demands;

use App\Models\Branch;
use App\Models\Demand;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Branch Demands')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: null)]
    public ?int $branch = null;

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedBranch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Demand::query()
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->branch, fn ($q) => $q->where('branch_id', $this->branch));

        $counts = [
            'all' => Demand::count(),
            'pending' => Demand::where('status', 'pending')->count(),
            'in_preparation' => Demand::where('status', 'in_preparation')->count(),
            'completed' => Demand::where('status', 'completed')->count(),
        ];

        return view('livewire.demands.index', [
            'demands' => (clone $query)->with(['branch', 'batch', 'transfer'])
                ->withCount('lines')
                ->latest('demand_date')
                ->latest('id')
                ->paginate(20),
            'branches' => Branch::orderBy('name')->get(),
            'counts' => $counts,
        ]);
    }
}
