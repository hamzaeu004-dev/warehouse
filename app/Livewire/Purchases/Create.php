<?php

namespace App\Livewire\Purchases;

use App\Livewire\Items\Index as ItemsIndex;
use App\Models\Category;
use App\Models\Item;
use App\Models\Purchase;
use App\Services\ActivityLogger;
use App\Services\StockService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * New purchase, or editing a saved one (/purchases/{purchase}/edit).
 */
class Create extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $purchase_date = '';

    public string $store_name = '';

    public string $notes = '';

    public function mount(?Purchase $purchase = null): void
    {
        $this->purchase_date = now()->toDateString();

        if ($purchase?->exists) {
            $this->editingId = $purchase->id;
            $this->purchase_date = $purchase->purchase_date->toDateString();
            $this->store_name = (string) $purchase->store_name;
            $this->notes = (string) $purchase->notes;
        }
    }

    private function editing(): ?Purchase
    {
        return $this->editingId ? Purchase::findOrFail($this->editingId) : null;
    }

    /**
     * Quick-add an item that is not in the list yet. Returns it in the
     * same shape the item picker uses.
     */
    public function createItem(string $name, string $unit, ?int $categoryId = null): array
    {
        // "Other" may have been renamed or deleted on the Categories page, so bring it back if needed
        $categoryId ??= Category::firstOrCreate(['name' => 'Other'], ['sort_order' => (int) Category::max('sort_order') + 1])->id;
        $name = trim($name);

        Validator::make(['name' => $name, 'unit' => $unit, 'category_id' => $categoryId], [
            'name' => ['required', 'string', 'max:150', Rule::unique('items')->where('category_id', $categoryId)],
            'unit' => ['required', 'string', 'max:30'],
            'category_id' => ['required', 'exists:categories,id'],
        ], ['name.unique' => 'This item already exists.'])->validate();

        $item = Item::create(['name' => $name, 'unit' => $unit, 'category_id' => $categoryId])->load('category');
        ActivityLogger::record('item.created', "New item added from purchase screen: {$item->name} ({$item->category->name}, {$item->unit})", $item);

        return Pickable::item($item);
    }

    public function save(array $lines, StockService $stock)
    {
        $purchase = $this->editing();
        // Items already on the purchase may stay even if they were hidden since
        $keep = $purchase ? $purchase->lines()->pluck('item_id')->all() : [];

        $data = Validator::make(
            ['purchase_date' => $this->purchase_date, 'store_name' => $this->store_name, 'notes' => $this->notes, 'lines' => $lines],
            [
                'purchase_date' => ['required', 'date', 'before_or_equal:today'],
                'store_name' => ['nullable', 'string', 'max:150'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'lines' => ['required', 'array', 'min:1', 'max:200'],
                'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $keep))],
                'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:100000'],
                'lines.*.pieces' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            ],
            [
                'lines.required' => 'Add at least one item.',
                'lines.*.item_id.exists' => 'An item in the list is hidden or was removed. Take it out of the list.',
                'lines.min' => 'Add at least one item.',
                'lines.*.qty.required' => 'Enter a quantity for every item.',
                'lines.*.qty.gt' => 'Quantity must be greater than 0.',
                'lines.*.qty.max' => 'Quantity is too large. Check the number.',
                'lines.*.unit_price.required' => 'Enter a price for every item.',
                'lines.*.unit_price.max' => 'Price is too large. Check the number.',
                'purchase_date.before_or_equal' => 'Date cannot be in the future.',
            ]
        )->validate();

        if ($purchase) {
            $purchase = $stock->updatePurchase($purchase, $data, $data['lines']);
            session()->flash('success', 'Purchase updated. Stock corrected.');
        } else {
            $purchase = $stock->recordPurchase($data, $data['lines']);
            session()->flash('success', 'Purchase saved. Stock updated.');
        }

        $this->redirectRoute('purchases.show', $purchase, navigate: true);

        // Tells the form the save worked, so it drops its local draft
        return true;
    }

    public function render()
    {
        $purchase = $this->editing();
        $lines = $purchase?->lines()->get() ?? collect();

        return view('livewire.purchases.create', [
            'items' => Pickable::all($lines->pluck('item_id')->all()),
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
            'units' => ItemsIndex::units(),
            'initialRows' => $purchase ? $lines->map(fn ($l) => [
                'item_id' => $l->item_id, 'qty' => (float) $l->qty, 'pieces' => $l->pieces !== null ? (float) $l->pieces : '', 'price' => (float) $l->unit_price,
            ])->values()->all() : null,
            'isEditing' => (bool) $purchase,
        ])->title($purchase ? "Edit Purchase #{$purchase->id}" : 'New Purchase');
    }
}
