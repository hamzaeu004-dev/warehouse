<?php

namespace App\Livewire\Demands;

use App\Models\Demand;
use App\Services\StockService;
use Livewire\Component;

class Show extends Component
{
    public Demand $demand;

    public function mount(Demand $demand): void
    {
        $this->demand = $demand->load(['branch', 'lines.item.category', 'batch', 'transfer']);
    }

    public function cancel(StockService $stock)
    {
        $stock->cancelDemand($this->demand);
        $this->demand->refresh();
        session()->flash('success', "Demand {$this->demand->demand_no} cancelled.");
    }

    public function delete(StockService $stock)
    {
        $stock->deleteDemand($this->demand);
        session()->flash('success', "Demand {$this->demand->demand_no} deleted.");

        return $this->redirectRoute('demands.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.demands.show', [
            'demand' => $this->demand,
            'lines' => $this->demand->lines,
        ])->title("Demand {$this->demand->demand_no}");
    }
}
