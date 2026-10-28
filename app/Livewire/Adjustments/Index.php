<?php

namespace App\Livewire\Adjustments;

use App\Models\Adjustment;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Adjustments')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $reason = '';

    public function updatedReason(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.adjustments.index', [
            'reasons' => Adjustment::REASONS,
            'adjustments' => Adjustment::withCount('lines')
                ->when($this->reason !== '', fn ($q) => $q->where('reason', $this->reason))
                ->latest('adjustment_date')->latest('id')->paginate(20),
        ]);
    }
}
