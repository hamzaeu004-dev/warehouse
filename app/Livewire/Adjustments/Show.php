<?php

namespace App\Livewire\Adjustments;

use App\Models\Adjustment;
use App\Services\StockService;
use Livewire\Component;

class Show extends Component
{
    public Adjustment $adjustment;

    public function delete(StockService $stock)
    {
        $stock->deleteAdjustment($this->adjustment);
        session()->flash('success', 'Adjustment deleted. Stock restored.');

        return $this->redirectRoute('adjustments.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.adjustments.show', [
            'lines' => $this->adjustment->lines()->with('item')->get(),
        ])->title('Adjustment #'.$this->adjustment->id);
    }
}
