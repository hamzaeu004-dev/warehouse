<?php

namespace App\Livewire\Batches;

use App\Models\Batch;
use App\Services\StockService;
use Livewire\Component;

class Show extends Component
{
    public Batch $batch;

    public function mount(Batch $batch): void
    {
        $this->batch = $batch->load(['lines.item.category', 'transfer.branch']);
    }

    public function delete(StockService $stock)
    {
        $stock->deleteBatch($this->batch);
        session()->flash('success', "Batch {$this->batch->batch_no} deleted. Stock returned to warehouse.");

        return $this->redirectRoute('batches.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.batches.show', [
            'batch' => $this->batch,
            'lines' => $this->batch->lines,
        ])->title("Batch {$this->batch->batch_no}");
    }
}
