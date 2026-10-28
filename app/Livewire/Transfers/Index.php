<?php

namespace App\Livewire\Transfers;

use App\Models\Branch;
use App\Models\Transfer;
use App\Services\InvoiceStatus;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Stock Transfers')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: null)]
    public ?int $branch = null;

    public function updatedBranch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Transfer::query()
            ->when($this->branch, fn ($q) => $q->where('branch_id', $this->branch));

        return view('livewire.transfers.index', [
            'branches' => Branch::orderBy('id')->get(),
            'totalValue' => (float) (clone $query)->sum('total_value'),
            'transfers' => (clone $query)->with('branch')->withCount('lines')
                ->latest('transfer_date')->latest('id')->paginate(20),
        ]);
    }
}
