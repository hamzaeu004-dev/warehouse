<?php

namespace App\Livewire\Items;

use App\Models\Category;
use App\Models\Item;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Show extends Component
{
    public Item $item;

    public bool $editing = false;

    public string $name = '';

    public ?int $category_id = null;

    public string $unit = '';

    public ?string $min_stock = null;

    public ?string $sale_rate = null;

    public function mount(Item $item): void
    {
        $this->item = $item;
    }

    public function edit(): void
    {
        $this->name = $this->item->name;
        $this->category_id = (int) $this->item->category_id;
        $this->unit = $this->item->unit;
        $this->min_stock = $this->item->min_stock !== null ? (string) (float) $this->item->min_stock : null;
        $this->sale_rate = $this->item->sale_rate !== null ? (string) (float) $this->item->sale_rate : null;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->min_stock = $this->min_stock === '' ? null : $this->min_stock;
        $this->sale_rate = $this->sale_rate === '' ? null : $this->sale_rate;
        $data = $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('items')->where('category_id', $this->category_id)->ignore($this->item->id)],
            'category_id' => ['required', 'exists:categories,id'],
            'unit' => ['required', 'string', 'max:30'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'sale_rate' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ], ['name.unique' => 'This item already exists in this category.']);

        $before = $this->item->only(['name', 'category_id', 'unit', 'min_stock', 'sale_rate']);
        $data['category_id'] = (int) $data['category_id'];
        $this->item->update(['name' => trim($data['name'])] + $data);
        $this->item->refresh();
        $this->item->load('category');
        $this->category_id = (int) $this->item->category_id;

        $changes = $this->describeChanges($before, $this->item->only(['name', 'category_id', 'unit', 'min_stock', 'sale_rate']));
        if ($changes) {
            ActivityLogger::record('item.updated', "Item updated: {$before['name']}. ".implode(', ', $changes), $this->item);
        }

        $this->editing = false;
        session()->flash('success', 'Item updated.');
    }

    public function delete()
    {
        if ($this->item->purchaseItems()->exists() || $this->item->transferItems()->exists() || $this->item->adjustmentItems()->exists() || $this->item->batchItems()->exists()) {
            $this->addError('delete', 'This item has stock history and cannot be deleted.');

            return null;
        }

        ActivityLogger::record('item.deleted', "Item deleted: {$this->item->name} ({$this->item->category->name})", $this->item);
        $this->item->delete();
        session()->flash('success', 'Item deleted.');

        return $this->redirectRoute('items.index', navigate: true);
    }

    /**
     * Hidden items keep all their history but no longer show up when adding
     * purchases, invoices or adjustments, or in the normal stock list.
     */
    public function toggleHidden(): void
    {
        $hide = $this->item->is_active;
        $this->item->update(['is_active' => ! $hide]);

        if ($hide) {
            $left = (float) $this->item->stock_qty > 0 ? ' ('.qty($this->item->stock_qty)." {$this->item->unit} still in stock)" : '';
            ActivityLogger::record('item.hidden', "Item hidden (no longer used): {$this->item->name}{$left}", $this->item);
            session()->flash('success', 'Item hidden. Its history is kept.');
        } else {
            ActivityLogger::record('item.shown', "Item back in use: {$this->item->name}", $this->item);
            session()->flash('success', 'Item is back in use.');
        }
    }

    /**
     * "unit kg → packet" style descriptions of what an edit changed.
     */
    private function describeChanges(array $before, array $after): array
    {
        $show = fn ($field, $value) => match (true) {
            $value === null || $value === '' => 'none',
            $field === 'category_id' => Category::find($value)?->name ?? '?',
            $field === 'min_stock' => qty($value),
            $field === 'sale_rate' => money($value),
            default => (string) $value,
        };
        $labels = ['name' => 'name', 'category_id' => 'category', 'unit' => 'unit', 'min_stock' => 'min stock', 'sale_rate' => 'selling rate'];

        $changes = [];
        foreach ($labels as $field => $label) {
            $old = $show($field, $before[$field]);
            $new = $show($field, $after[$field]);
            if ($old !== $new) {
                $changes[] = "{$label} {$old} → {$new}";
            }
        }

        return $changes;
    }

    public function render()
    {
        // qty is signed: + stock came in, - stock went out
        $purchases = $this->item->purchaseItems()->with('purchase')->latest('id')->limit(20)->get()
            ->map(fn ($l) => ['title' => 'Purchase', 'date' => $l->purchase->purchase_date, 'qty' => (float) $l->qty, 'rate' => $l->unit_price, 'url' => route('purchases.show', $l->purchase_id), 'at' => $l->created_at]);
        $transfers = $this->item->transferItems()->with('transfer.branch')->latest('id')->limit(20)->get()
            ->map(fn ($l) => ['title' => 'Sent to: '.$l->transfer->branch->name, 'date' => $l->transfer->transfer_date, 'qty' => -(float) $l->qty, 'rate' => $l->unit_cost, 'url' => route('transfers.show', $l->transfer_id), 'at' => $l->created_at]);
        $adjustments = $this->item->adjustmentItems()->with('adjustment')->latest('id')->limit(20)->get()
            ->map(fn ($l) => ['title' => 'Adjusted: '.$l->adjustment->reasonLabel(), 'date' => $l->adjustment->adjustment_date, 'qty' => (float) $l->qty_change, 'rate' => $l->unit_cost, 'url' => route('adjustments.show', $l->adjustment_id), 'at' => $l->created_at]);
        $batches = $this->item->batchItems()->with('batch')->latest('id')->limit(20)->get()
            ->map(fn ($l) => ['title' => 'Batch: '.($l->batch->batch_no ?? '#'.$l->batch_id), 'date' => $l->batch->batch_date, 'qty' => -(float) $l->qty, 'rate' => $l->unit_cost, 'url' => route('batches.show', $l->batch_id), 'at' => $l->created_at]);

        return view('livewire.items.show', [
            'hasHistory' => $purchases->isNotEmpty() || $transfers->isNotEmpty() || $adjustments->isNotEmpty() || $batches->isNotEmpty(),
            'history' => $purchases->concat($transfers)->concat($adjustments)->concat($batches)
                ->sortByDesc(fn ($h) => $h['date']->format('Ymd').$h['at']->format('His'))
                ->take(30)->values(),
            'categories' => Category::orderBy('sort_order')->get(),
            'units' => Index::units(),
        ])->title($this->item->name);
    }
}
