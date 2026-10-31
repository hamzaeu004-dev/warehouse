<?php

namespace App\Livewire\Items;

use App\Models\Category;
use App\Models\Item;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Stock')]
class Index extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'cat', except: null)]
    public ?int $category = null;

    #[Url(as: 'filter', except: '')]
    public string $filter = '';

    public int $perPage = 100;

    public bool $showForm = false;

    public string $name = '';

    public ?int $category_id = null;

    public string $unit = 'kg';

    public function showMore(): void
    {
        $this->perPage += 100;
    }

    public function openForm(): void
    {
        $this->resetValidation();
        $this->reset('name');
        // On a fresh start there are no categories yet, so give new items a home
        if (! Category::exists()) {
            Category::create(['name' => 'Other', 'sort_order' => 1]);
        }
        $catId = $this->category ?? Category::orderBy('sort_order')->value('id');
        $this->category_id = $catId !== null ? (int) $catId : null;
        $this->showForm = true;
    }

    public function create(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('items')->where('category_id', $this->category_id)],
            'category_id' => ['required', 'exists:categories,id'],
            'unit' => ['required', 'string', 'max:30'],
        ], ['name.unique' => 'This item already exists in this category.']);

        $data['category_id'] = (int) $data['category_id'];
        $item = Item::create(['name' => trim($data['name'])] + $data)->load('category');
        ActivityLogger::record('item.created', "New item added: {$item->name} ({$item->category->name}, {$item->unit})", $item);
        $this->showForm = false;
        session()->flash('success', 'New item added.');
    }

    public function render()
    {
        $query = Item::query()
            ->where('is_active', $this->filter !== 'hidden')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
            ->when($this->filter === 'in', fn ($q) => $q->where('stock_qty', '>', 0))
            ->when($this->filter === 'out', fn ($q) => $q->where('stock_qty', '<=', 0))
            ->when($this->filter === 'low', fn ($q) => $q->whereNotNull('min_stock')->whereColumn('stock_qty', '<=', 'min_stock'));

        return view('livewire.items.index', [
            'items' => (clone $query)->with('category')->orderBy('name')->limit($this->perPage)->get(),
            'totalCount' => (clone $query)->count(),
            'totalValue' => (float) (clone $query)->sum(DB::raw('stock_qty * avg_cost')),
            'categories' => Category::orderBy('sort_order')->get(),
            'units' => self::units(),
        ]);
    }

    public static function units(): array
    {
        return ['kg', 'gram', 'litre', 'ml', 'pcs', 'packet', 'box', 'bottle', 'roll', 'dozen', 'carton', 'bag', 'jar', 'tin'];
    }
}
