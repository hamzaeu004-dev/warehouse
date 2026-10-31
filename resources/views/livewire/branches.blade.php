<div class="space-y-4">
    @error('delete') <div class="notice-error">{{ $message }}</div> @enderror

    @if ($branches->isEmpty())
        <div class="empty-state">
            <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
            <p class="font-medium text-slate-700">No branches yet</p>
            <p>Add each restaurant branch below. You need at least one before you can send stock.</p>
        </div>
    @endif

    <div class="card divide-y divide-slate-100 p-0 {{ $branches->isEmpty() ? 'hidden' : '' }}">
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

    <form wire:submit="add" class="card space-y-3">
        <div>
            <h2 class="card-title">Add a branch</h2>
            <p class="text-xs text-slate-500">These details are printed on the invoice under "Deliver To".</p>
        </div>
        <div>
            <label class="label" for="newName">Branch name</label>
            <input id="newName" wire:model="newName" class="input" placeholder="e.g. ROOSTERS - RAHEEM GARDEN">
            @error('newName') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label" for="newContact">Contact (ATTN)</label>
                <input id="newContact" wire:model="newContact" class="input" placeholder="e.g. Abdul Mateen">
            </div>
            <div>
                <label class="label" for="newPhone">Phone</label>
                <input id="newPhone" wire:model="newPhone" inputmode="tel" class="input" placeholder="Optional">
            </div>
        </div>
        <div>
            <label class="label" for="newAddress">Address</label>
            <input id="newAddress" wire:model="newAddress" class="input" placeholder="e.g. Raheem Garden G.T Road">
        </div>
        <button class="btn-primary w-full">+ Add branch</button>
    </form>
</div>
