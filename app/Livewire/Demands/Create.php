<?php

namespace App\Livewire\Demands;

use App\Livewire\Purchases\Pickable;
use App\Models\Branch;
use App\Models\Demand;
use App\Services\StockService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New Demand')]
class Create extends Component
{
    public ?int $branch_id = null;

    public string $demand_no = '';

    public string $demand_date = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->demand_date = now()->toDateString();
        $this->demand_no = Demand::nextDemandNo();
        $this->branch_id = request()->integer('branch') ?: null;
    }

    public function save(array $lines, StockService $stock)
    {
        $data = Validator::make(
            [
                'branch_id' => $this->branch_id,
                'demand_no' => $this->demand_no,
                'demand_date' => $this->demand_date,
                'notes' => $this->notes,
                'lines' => $lines,
            ],
            [
                'branch_id' => ['required', 'exists:branches,id'],
                'demand_no' => ['nullable', 'string', 'max:50'],
                'demand_date' => ['required', 'date', 'before_or_equal:today'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'lines' => ['required', 'array', 'min:1', 'max:200'],
                'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
                'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:100000'],
                'lines.*.unit' => ['nullable', 'string', 'max:30'],
                'lines.*.notes' => ['nullable', 'string', 'max:255'],
            ],
            [
                'branch_id.required' => 'Please select the branch demanding items.',
                'lines.required' => 'Add at least one item.',
                'lines.min' => 'Add at least one item.',
                'lines.*.qty.required' => 'Enter requested quantity for every item.',
                'lines.*.qty.gt' => 'Requested quantity must be greater than 0.',
                'demand_date.before_or_equal' => 'Date cannot be in the future.',
            ]
        )->validate();

        $demand = $stock->recordDemand($data, $data['lines']);
        session()->flash('success', "Demand {$demand->demand_no} saved from {$demand->branch->name}.");

        $this->redirectRoute('demands.show', $demand, navigate: true);

        return true;
    }

    public function render()
    {
        return view('livewire.demands.create', [
            'branches' => Branch::orderBy('id')->get(),
            'items' => Pickable::all(),
        ]);
    }
}
