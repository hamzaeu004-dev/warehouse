<?php

namespace App\Livewire\Adjustments;

use App\Livewire\Purchases\Pickable;
use App\Models\Adjustment;
use App\Services\StockService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Adjust Stock')]
class Create extends Component
{
    public string $reason = 'wastage';

    public string $adjustment_date = '';

    public string $notes = '';

    public ?int $startItem = null;

    public function mount(): void
    {
        $this->adjustment_date = now()->toDateString();
        $this->startItem = request()->integer('item') ?: null;

        if (array_key_exists(request()->query('reason'), Adjustment::REASONS)) {
            $this->reason = request()->query('reason');
        }
    }

    public function save(array $lines, StockService $stock)
    {
        $isCount = $this->reason === 'count';

        $data = Validator::make(
            ['reason' => $this->reason, 'adjustment_date' => $this->adjustment_date, 'notes' => $this->notes, 'lines' => $lines],
            [
                'reason' => ['required', Rule::in(array_keys(Adjustment::REASONS))],
                'adjustment_date' => ['required', 'date', 'before_or_equal:today'],
                'notes' => [Rule::requiredIf($this->reason === 'other'), 'nullable', 'string', 'max:1000'],
                'lines' => ['required', 'array', 'min:1', 'max:200'],
                'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('is_active', true)],
                'lines.*.qty' => $isCount ? ['prohibited'] : ['required', 'numeric', 'gt:0', 'max:100000'],
                'lines.*.counted_qty' => $isCount ? ['required', 'numeric', 'min:0', 'max:100000'] : ['prohibited'],
            ],
            [
                'notes.required' => 'Write what happened when the reason is Other.',
                'lines.required' => 'Add at least one item.',
                'lines.*.item_id.exists' => 'An item in the list is hidden or was removed. Take it out of the list.',
                'lines.min' => 'Add at least one item.',
                'lines.*.item_id.distinct' => 'Each item can only be added once.',
                'lines.*.qty.required' => 'Enter a quantity for every item.',
                'lines.*.qty.gt' => 'Quantity must be greater than 0.',
                'lines.*.qty.max' => 'Quantity is too large. Check the number.',
                'lines.*.counted_qty.required' => 'Enter the counted quantity for every item.',
                'lines.*.counted_qty.max' => 'Quantity is too large. Check the number.',
                'adjustment_date.before_or_equal' => 'Date cannot be in the future.',
            ]
        )->validate();

        $adjustment = $stock->recordAdjustment($data, $data['lines']);

        session()->flash('success', 'Stock adjusted.');

        $this->redirectRoute('adjustments.show', $adjustment, navigate: true);

        // Tells the form the save worked, so it drops its local draft
        return true;
    }

    public function render()
    {
        return view('livewire.adjustments.create', [
            'items' => Pickable::all(),
            'reasons' => Adjustment::REASONS,
        ]);
    }
}
