<?php

namespace App\Livewire\Batches;

use App\Livewire\Purchases\Pickable;
use App\Models\Batch;
use App\Services\StockService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New Batch')]
class Create extends Component
{
    public string $batch_date = '';

    public string $batch_no = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->batch_date = now()->toDateString();
        $this->batch_no = Batch::nextBatchNo();
    }

    public function save(array $lines, StockService $stock)
    {
        $data = Validator::make(
            [
                'batch_date' => $this->batch_date,
                'batch_no' => $this->batch_no,
                'notes' => $this->notes,
                'lines' => $lines,
            ],
            [
                'batch_date' => ['required', 'date', 'before_or_equal:today'],
                'batch_no' => ['nullable', 'string', 'max:50'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'lines' => ['required', 'array', 'min:1', 'max:200'],
                'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
                'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:100000'],
                'lines.*.unit' => ['nullable', 'string', 'max:30'],
            ],
            [
                'lines.required' => 'Add at least one item from stock.',
                'lines.min' => 'Add at least one item from stock.',
                'lines.*.qty.required' => 'Enter a quantity for every item.',
                'lines.*.qty.gt' => 'Quantity must be greater than 0.',
                'batch_date.before_or_equal' => 'Date cannot be in the future.',
            ]
        )->validate();

        $batch = $stock->recordBatch($data, $data['lines']);
        session()->flash('success', "Batch {$batch->batch_no} created. Stock updated.");

        $this->redirectRoute('batches.show', $batch, navigate: true);

        return true;
    }

    public function render()
    {
        return view('livewire.batches.create', [
            'items' => Pickable::all(),
        ]);
    }
}
