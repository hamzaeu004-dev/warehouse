<div class="space-y-4">
    @error('delete') <div class="notice-error">{{ $message }}</div> @enderror

    <div class="card divide-y divide-slate-100 p-0">
        @foreach ($branches as $branch)
            <div class="px-4 py-3" wire:key="branch-{{ $branch->id }}">
                @if ($editingId === $branch->id)
                    <form wire:submit="update" class="space-y-2">
                        <div>
                            <label class="label">Branch name (as on invoice)</label>
                            <input wire:model="editName" class="input" placeholder="e.g. ROOSTERS - RAHEEM GARDEN" autofocus>
                            @error('editName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Contact person (ATTN)</label>
                            <input wire:model="editContact" class="input" placeholder="e.g. Abdul Mateen">
                        </div>
                        <div>
                            <label class="label">Address</label>
                            <input wire:model="editAddress" class="input" placeholder="e.g. Raheem Garden G.T Road">
                        </div>
                        <div>
                            <label class="label">Phone (optional)</label>
                            <input wire:model="editPhone" inputmode="tel" class="input">
                        </div>
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <button type="button" wire:click="$set('editingId', null)" class="btn-light">Cancel</button>
                            <button class="btn-primary">Save</button>
                        </div>
                    </form>
                @else
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ route('branches.show', $branch) }}" wire:navigate class="group -my-1 min-w-0 flex-1 rounded-lg py-1">
                            <p class="font-medium group-hover:text-teal-700 group-hover:underline">{{ $branch->name }} <span class="text-slate-400">›</span></p>
                            @if ($branch->contact_person || $branch->address)
                                <p class="truncate text-xs text-slate-600">{{ collect([$branch->contact_person ? 'ATTN: '.$branch->contact_person : null, $branch->address])->filter()->implode(' · ') }}</p>
                            @endif
                            <p class="text-xs text-slate-500">{{ $branch->transfers_count }} {{ Str::plural('transfer', $branch->transfers_count) }} · total delivered {{ money($branch->transfers_sum_total_value ?? 0) }}</p>
                        </a>
                        <div class="flex gap-1">
                            <button wire:click="edit({{ $branch->id }})" class="rounded-lg px-3 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50 active:bg-teal-100">Edit</button>
                            @if ($branch->transfers_count === 0)
                                <button wire:click="delete({{ $branch->id }})" wire:confirm="Delete {{ $branch->name }}?" class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 active:bg-red-100">Delete</button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <form wire:submit="add" class="card space-y-2">
        <label class="label">New branch</label>
        <div class="flex gap-2">
            <input wire:model="newName" class="input" placeholder="Branch name">
            <button class="btn-primary px-4">Add</button>
        </div>
        @error('newName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </form>
</div>
