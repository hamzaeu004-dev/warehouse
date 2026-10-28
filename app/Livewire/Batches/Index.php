<?php

namespace App\Livewire\Batches;

use App\Models\Batch;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Batches')]
class Index extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.batches.index', [
            'batches' => Batch::with(['transfer.branch'])
                ->withCount('lines')
                ->latest('batch_date')
                ->latest('id')
                ->paginate(25),
        ]);
    }
}
