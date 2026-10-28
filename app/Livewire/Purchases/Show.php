<?php

namespace App\Livewire\Purchases;

use App\Models\Purchase;
use App\Services\StockService;
use Livewire\Component;

class Show extends Component
{
    public Purchase $purchase;

    public function delete(StockService $stock)
    {
        $stock->deletePurchase($this->purchase);
        session()->flash('success', 'Purchase deleted. Stock has been reduced.');

        return $this->redirectRoute('purchases.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.purchases.show', [
            'lines' => $this->purchase->lines()->with('item')->get(),
        ])->title('Purchase #'.$this->purchase->id);
    }
}
