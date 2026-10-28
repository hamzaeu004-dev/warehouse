<div class="space-y-4">
    <p class="px-1 text-sm text-slate-500">Categories group your items on the Stock page. Use the arrows to change their order.</p>

    <div class="card divide-y divide-slate-100 p-0">
        @foreach ($categories as $category)
            <div class="px-4 py-3" wire:key="cat-{{ $category->id }}">
                @if ((int) $editingId === (int) $category->id)
                    <form wire:submit="update" class="space-y-2">
                        <input wire:model="editName" class="input" autofocus>
                        @error('editName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="$set('editingId', null)" class="btn-light">Cancel</button>
                            <button class="btn-primary">Save</button>
                        </div>
                    </form>
                @elseif ((int) $deletingId === (int) $category->id)
                    <div class="space-y-2">
                        @if ((int) $category->items_count > 0)
                            <p class="text-sm">
                                <b>{{ $category->name }}</b> has {{ $category->items_count }} {{ Str::plural('item', $category->items_count) }}.
                                Move them to another category, then it will be deleted.
                            </p>
                            <select wire:model="moveTo" class="input">
                                @foreach ($categories->where('id', '!=', $category->id) as $other)
                                    <option value="{{ $other->id }}">Move to {{ $other->name }}</option>
                                @endforeach
                            </select>
                            @error('moveTo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="$set('deletingId', null)" class="btn-light">Cancel</button>
                                <button type="button" wire:click="moveItemsAndDelete" class="btn-danger">Move & delete</button>
                            </div>
                        @else
                            <p class="text-sm">
                                Delete category <b>{{ $category->name }}</b>?
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="$set('deletingId', null)" class="btn-light">Cancel</button>
                                <button type="button" wire:click="delete({{ $category->id }})" class="btn-danger">Delete</button>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <div class="flex flex-col">
                            <button wire:click="move({{ $category->id }}, -1)" @disabled($loop->first) aria-label="Move up"
                                    class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-teal-700 disabled:invisible">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            </button>
                            <button wire:click="move({{ $category->id }}, 1)" @disabled($loop->last) aria-label="Move down"
                                    class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-teal-700 disabled:invisible">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </div>
                        <a href="{{ route('items.index', ['cat' => $category->id]) }}" wire:navigate class="group min-w-0 flex-1">
                            <p class="font-medium group-hover:text-teal-700 group-hover:underline">{{ $category->name }} <span class="text-slate-400">›</span></p>
                            <p class="text-xs text-slate-500">
                                {{ $category->items_count }} {{ Str::plural('item', $category->items_count) }}
                                @if ($category->hidden_count) · {{ $category->hidden_count }} hidden @endif
                            </p>
                        </a>
                        <button wire:click="edit({{ $category->id }})" class="rounded-lg px-3 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50 active:bg-teal-100">Rename</button>
                        <button wire:click="promptDelete({{ $category->id }})" class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 active:bg-red-100">Delete</button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <form wire:submit="add" class="card space-y-2">
        <label class="label">New category</label>
        <div class="flex gap-2">
            <input wire:model="newName" class="input" placeholder="e.g. Vegetables">
            <button class="btn-primary px-4">Add</button>
        </div>
        @error('newName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </form>
</div>
