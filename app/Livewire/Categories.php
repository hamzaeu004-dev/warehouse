<?php

namespace App\Livewire;

use App\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Categories')]
class Categories extends Component
{
    public string $newName = '';

    public ?int $editingId = null;

    public string $editName = '';

    // Category being deleted that still has items, and where to move them
    public ?int $deletingId = null;

    public ?int $moveTo = null;

    public function add(): void
    {
        $this->validate(['newName' => ['required', 'string', 'max:60', 'unique:categories,name']], [], ['newName' => 'category name']);

        $category = Category::create([
            'name' => trim($this->newName),
            'sort_order' => (int) Category::max('sort_order') + 1,
        ]);
        ActivityLogger::record('category.created', "Category added: {$category->name}", $category);
        $this->reset('newName');
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $this->deletingId = null;
        $this->editingId = (int) $id;
        $this->editName = Category::findOrFail($id)->name;
    }

    public function update(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:60', Rule::unique('categories', 'name')->ignore($this->editingId)],
        ], [], ['editName' => 'category name']);

        $category = Category::findOrFail($this->editingId);
        $old = $category->name;
        $category->update(['name' => trim($this->editName)]);
        if ($old !== $category->name) {
            ActivityLogger::record('category.updated', "Category renamed: {$old} → {$category->name}", $category);
        }
        $this->reset('editingId', 'editName');
    }

    // Swap places with the neighbour above (-1) or below (+1)
    public function move(int $id, int $direction): void
    {
        $id = (int) $id;
        $ordered = Category::orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $ordered->search(fn ($c) => (int) $c->id === $id);
        $target = $index + ($direction < 0 ? -1 : 1);
        if ($index === false || ! isset($ordered[$target])) {
            return;
        }

        $list = $ordered->all();
        [$list[$index], $list[$target]] = [$list[$target], $list[$index]];

        DB::transaction(function () use ($list) {
            foreach ($list as $position => $category) {
                $category->update(['sort_order' => $position + 1]);
            }
        });
    }

    public function promptDelete(int $id): void
    {
        $id = (int) $id;
        $this->resetValidation();
        $this->editingId = null;
        $this->deletingId = $id;

        $category = Category::withCount('items')->findOrFail($id);
        if ((int) $category->items_count > 0) {
            $this->moveTo = (int) (Category::where('id', '!=', $id)->orderBy('sort_order')->value('id') ?? 0);
        }
    }

    public function delete(int $id): void
    {
        $id = (int) $id;
        $category = Category::withCount('items')->findOrFail($id);

        if ((int) $category->items_count > 0) {
            // Ask where its items should go first
            $this->deletingId = $id;
            $this->moveTo = (int) (Category::where('id', '!=', $id)->orderBy('sort_order')->value('id') ?? 0);

            return;
        }

        ActivityLogger::record('category.deleted', "Category deleted: {$category->name}", $category);
        $category->delete();
        $this->reset('deletingId', 'moveTo');
    }

    public function moveItemsAndDelete(): void
    {
        $category = Category::withCount('items')->findOrFail($this->deletingId);
        $this->validate([
            'moveTo' => ['required', 'integer', Rule::exists('categories', 'id'), Rule::notIn([$category->id])],
        ], ['moveTo.required' => 'Choose where the items should go.'], ['moveTo' => 'category']);

        $target = Category::findOrFail($this->moveTo);
        $clashes = $category->items()->whereIn('name', $target->items()->pluck('name'))->pluck('name');
        if ($clashes->isNotEmpty()) {
            $this->addError('moveTo', "{$target->name} already has: ".$clashes->implode(', ').'. Rename those items first.');

            return;
        }

        DB::transaction(function () use ($category, $target) {
            $category->items()->update(['category_id' => $target->id]);
            ActivityLogger::record(
                'category.deleted',
                "Category deleted: {$category->name}. ".ActivityLogger::count($category->items_count)." moved to {$target->name}",
                $category
            );
            $category->delete();
        });

        $this->reset('deletingId', 'moveTo');
    }

    public function render()
    {
        return view('livewire.categories', [
            'categories' => Category::withCount([
                'items',
                'items as hidden_count' => fn ($q) => $q->where('is_active', false),
            ])->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}
