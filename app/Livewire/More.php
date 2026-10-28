<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('More')]
class More extends Component
{
    public function render()
    {
        return view('livewire.more');
    }
}
