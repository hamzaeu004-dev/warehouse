<div class="space-y-3">
    <div class="flex gap-2">
        <input wire:model.live.debounce.250ms="search" type="search" class="input" placeholder="Search items...">
        <button wire:click="openForm" class="btn-primary shrink-0 px-3" aria-label="New item">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 4v16m8-8H4"/></svg>
        </button>
    </div>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
        <button wire:click="$set('category', null)" class="chip shrink-0 rounded-full px-3 py-1.5 text-sm font-medium {{ $category === null ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">All</button>
        @foreach ($categories as $cat)
            <button wire:click="$set('category', {{ $cat->id }})" class="chip shrink-0 rounded-full px-3 py-1.5 text-sm font-medium {{ $category === $cat->id ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ $cat->name }}</button>
        @endforeach
    </div>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 text-sm">
        @foreach (['' => 'All', 'in' => 'In stock', 'out' => 'Out of stock', 'low' => 'Low stock', 'hidden' => 'Hidden'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')" class="chip shrink-0 rounded-lg px-2.5 py-1 font-medium {{ $filter === $key ? 'bg-slate-800 text-white' : 'text-slate-500' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="flex items-center justify-between text-xs">
        <p class="text-slate-500">{{ $totalCount }} {{ Str::plural('item', $totalCount) }}{{ $filter === 'hidden' ? ' hidden' : '' }} · value {{ money($totalValue) }}</p>
        <a href="{{ route('categories') }}" wire:navigate class="text-link">Manage categories →</a>
    </div>
    @if ($filter === 'hidden')
        <p class="text-xs text-slate-500">Hidden items are no longer used. Open one and tap "Use again" to bring it back.</p>
    @endif

    <div class="card divide-y divide-slate-100 p-0">
        @forelse ($items as $item)
            <a href="{{ route('items.show', $item) }}" wire:navigate wire:key="item-{{ $item->id }}" class="row-link flex items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ $item->name }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $item->category->name }}
                        @if ($item->last_price !== null) · last {{ money($item->last_price) }}/{{ $item->unit }} @endif
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="font-semibold {{ (float) $item->stock_qty <= 0 ? 'text-slate-400' : ($item->isLowStock() ? 'text-amber-600' : 'text-teal-700') }}">
                        {{ qty($item->stock_qty) }} <span class="text-xs font-normal">{{ $item->unit }}</span>
                    </p>
                </div>
            </a>
        @empty
            <p class="px-4 py-6 text-center text-sm text-slate-500">No items found.</p>
        @endforelse
    </div>

    @if ($totalCount > $items->count())
        <button wire:click="showMore" class="btn-light w-full">Show more ({{ $totalCount - $items->count() }} left)</button>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 sm:items-center" wire:click.self="$set('showForm', false)">
            <form wire:submit="create" class="w-full max-w-md space-y-3 rounded-t-2xl bg-white p-5 pb-8 sm:rounded-2xl">
                <h2 class="text-lg font-semibold">New item</h2>
                <div>
                    <label class="label">Name</label>
                    <input wire:model="name" class="input" autofocus>
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Category</label>
                        <select wire:model="category_id" class="input">
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((int) $cat->id === (int) $category_id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Unit</label>
                        <select wire:model="unit" class="input">
                            @foreach ($units as $u)
                                <option value="{{ $u }}">{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-light">Cancel</button>
                    <button class="btn-primary">Save</button>
                </div>
            </form>
        </div>
    @endif
</div>
