<div x-data="stockForm({ items: @js($items), mode: 'demand', draftKey: 'demand-draft-v1', fields: ['demand_date', 'demand_no', 'notes'] })" class="space-y-4 pb-24">
    <div class="card space-y-3">
        <div>
            <label class="label">Branch Demanding Items</label>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($branches as $branch)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model.live="branch_id" value="{{ $branch->id }}" class="peer sr-only">
                        <span class="flex h-full items-center justify-center rounded-xl border-2 border-slate-200 px-2 py-3 text-center text-sm font-semibold text-slate-600 transition hover:border-teal-400 peer-checked:border-teal-700 peer-checked:bg-teal-50 peer-checked:text-teal-800">
                            {{ $branch->name }}
                        </span>
                    </label>
                @endforeach
            </div>
            @error('branch_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label">Demand #</label>
                <input wire:model="demand_no" class="input font-semibold">
                @error('demand_no') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Demand Date</label>
                <input wire:model="demand_date" type="date" max="{{ now()->toDateString() }}" class="input">
                @error('demand_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <p class="text-xs text-slate-500">Tareeqa A: Branch specifies which items they require. The warehouse will prepare a batch or transfer.</p>
    </div>

    @include('livewire.partials.item-picker', ['allowCreate' => false])

    <template x-if="rows.length">
        <div class="space-y-2">
            <div class="flex items-center justify-between px-1">
                <p class="text-sm font-medium text-slate-600">
                    <span x-text="rows.length + (rows.length === 1 ? ' item requested' : ' items requested')"></span>
                </p>
                <button type="button" @click="clearAll" class="text-sm text-red-600 hover:underline">Clear all</button>
            </div>
            <template x-for="(row, index) in rows" :key="row.key">
                <div class="card p-3 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900" x-text="row.name"></p>
                            <p class="text-xs text-slate-500">
                                Current warehouse stock:
                                <span class="font-semibold" :class="row.stock > 0 ? 'text-emerald-700' : 'text-amber-700'" x-text="qty(row.stock) + ' ' + row.unit"></span>
                                <template x-if="isKg(row)">
                                    <span class="text-slate-400" x-text="' (' + qty(row.stock * 1000) + ' g)'"></span>
                                </template>
                            </p>
                        </div>
                        <button type="button" @click="remove(index)" class="-mr-1 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-red-600 active:bg-slate-200" aria-label="Remove">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-slate-500">Requested Quantity</label>
                            <div class="relative flex rounded-lg">
                                <input :id="`qty-${row.key}`" x-model="row.qty" inputmode="decimal" class="input px-2 text-right text-sm" :class="isKg(row) ? 'rounded-r-none pr-2' : 'pr-9'" placeholder="0">
                                <template x-if="isKg(row)">
                                    <select x-model="row.selected_unit" class="rounded-r-lg border border-l-0 border-slate-300 bg-slate-100 px-2 text-xs font-bold text-slate-700 hover:bg-slate-200 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 cursor-pointer">
                                        <option value="kg">kg</option>
                                        <option value="g">g</option>
                                    </select>
                                </template>
                                <template x-if="!isKg(row)">
                                    <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-slate-400" x-text="row.unit"></span>
                                </template>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Note / Instructions (optional)</label>
                            <input x-model="row.description" class="input text-xs" placeholder="e.g. urgent, mild spice">
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <div class="card">
        <label class="label">General Notes <span class="text-slate-400">(optional)</span></label>
        <textarea wire:model="notes" rows="2" class="input" placeholder="e.g. Needed before 4 PM for dinner service"></textarea>
        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    @if ($errors->any())
        <div class="notice-error">
            @foreach (array_unique($errors->all()) as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Bottom bar --}}
    <div class="card sticky bottom-16 z-20 flex items-center justify-between gap-3 bg-white/95 backdrop-blur shadow-lg border-teal-100">
        <div>
            <p class="text-xs text-slate-500">Items requested</p>
            <p class="text-lg font-bold text-slate-900" x-text="rows.length"></p>
        </div>
        <button type="button" @click="save" :disabled="!rows.length || saving"
                class="btn-primary px-6" :class="(!rows.length || saving) ? 'opacity-50 cursor-not-allowed' : ''">
            <span x-show="!saving">Save Demand</span>
            <span x-show="saving">Saving...</span>
        </button>
    </div>
</div>
