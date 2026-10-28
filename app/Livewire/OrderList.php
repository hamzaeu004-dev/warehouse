<?php

namespace App\Livewire;

use App\Services\OrderPlanner;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Order List')]
class OrderList extends Component
{
    #[Url(except: 'last_week')]
    public string $basis = 'last_week';

    public function render()
    {
        if (! array_key_exists($this->basis, OrderPlanner::BASES)) {
            $this->basis = 'last_week';
        }
        [$from, $to] = OrderPlanner::window($this->basis);
        $rows = OrderPlanner::suggestions($this->basis);

        return view('livewire.order-list', [
            'bases' => OrderPlanner::BASES,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            // Sent to the page so "Start purchase" can fill the purchase form
            'draftRows' => $rows->where('suggested', '>', 0)->map(fn ($r) => [
                'item_id' => $r->item->id, 'qty' => (float) $r->suggested, 'price' => $r->price ?: '',
            ])->values()->all(),
        ]);
    }
}
