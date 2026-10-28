<?php

namespace App\Livewire\Purchases;

use App\Models\Purchase;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Purchases')]
class Index extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.purchases.index', [
            'purchases' => Purchase::withCount('lines')->latest('purchase_date')->latest('id')->paginate(20),
        ]);
    }
}
