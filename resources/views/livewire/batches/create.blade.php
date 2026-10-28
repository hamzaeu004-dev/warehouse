<div x-data="stockForm({ items: @js($items), mode: 'batch', draftKey: 'batch-draft', fields: ['batch_date', 'batch_no', 'notes'] })" class="space-y-4 pb-20">
    <div class="card space-y-3">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label">Batch # / Name</label>
                <input wire:model="batch_no" type="text" class="input font-semibold" placeholder="e.g. B-0001 or Marination 1">
                @error('batch_no') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Date</label>
                <input wire:model="batch_date" type="date" max="{{ now()->toDateString() }}" class="input">
                @error('batch_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <p class="text-xs text-teal-700 font-medium">Branch ki demand ke mutabiq items add karein. Batch save hone ke baad 1-click se Branch ko send kar ke invoice ban jaye gi.</p>
    </div>

    @include('livewire.partials.item-picker', ['allowCreate' => false])

    <template x-if="rows.length">
        <div class="space-y-2">
            <div class="flex items-center justify-between px-1">
                <p class="text-sm font-medium text-slate-600">
                    <span x-text="rows.length + (rows.length === 1 ? ' item used' : ' items used')"></span>
                </p>
                <button type="button" @click="clearAll" class="text-sm text-red-600 hover:underline">Clear all</button>
            </div>
            <template x-for="(row, index) in rows" :key="row.key">
                <div class="card p-3" :class="overStock(row) ? 'ring-2 ring-red-400' : ''">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium" x-text="row.name"></p>
                            <p class="text-xs text-slate-500">
                                Stock available: <span class="font-semibold text-slate-700" x-text="qty(row.stock)"></span> <span x-text="row.unit"></span>
                                <template x-if="isKg(row)">
                                    <span class="text-slate-400" x-text="' (' + qty(row.stock * 1000) + ' g)'"></span>
                                </template>
                                <template x-if="row.cost > 0">
                                    <span> · <span x-text="money(row.cost)"></span>/<span x-text="row.unit"></span></span>
                                </template>
                            </p>
                        </div>
                        <div class="w-36 sm:w-40">
                            <label class="text-xs text-slate-500">Quantity used</label>
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
                        <button type="button" @click="remove(index)" class="mt-4 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-red-600 active:bg-slate-200" aria-label="Remove">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>
                    <template x-if="overStock(row)">
                        <p class="mt-1 text-xs font-semibold text-red-600">
                            Cannot use more than stock! Only <span x-text="qty(row.stock)"></span> <span x-text="row.unit"></span>
                            <template x-if="isKg(row)">
                                <span> (<span x-text="qty(row.stock * 1000)"></span> g)</span>
                            </template>
                            available.
                        </p>
                    </template>
                    <template x-if="!overStock(row) && row.qty !== ''">
                        <p class="mt-1 text-xs text-slate-500">
                            Using <span class="font-medium text-slate-700" x-text="qty(row.qty) + ' ' + (row.selected_unit || row.unit)"></span>
                            <template x-if="isKg(row) && row.selected_unit === 'g'">
                                <span class="text-slate-400" x-text="' (' + qty(baseQty(row)) + ' kg from stock)'"></span>
                            </template>
                            <template x-if="lineTotal(row) > 0">
                                <span> · Total: <span class="font-semibold text-slate-700" x-text="money(lineTotal(row))"></span></span>
                            </template>
                        </p>
                    </template>
                </div>
            </template>
        </div>
    </template>

    <div class="card">
        <label class="label">Notes <span class="text-slate-400">(optional)</span></label>
        <textarea wire:model="notes" rows="2" class="input" placeholder="e.g. 50kg chicken marination, morning batch, spicy recipe"></textarea>
        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    @if ($errors->any())
        <div class="notice-error">
            @foreach (array_unique($errors->all()) as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="fixed inset-x-0 bottom-16 z-20 border-t border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
            <div>
                <p class="text-xs text-slate-500">Total batch cost</p>
                <p class="text-lg font-bold text-slate-900" x-text="money(total)"></p>
            </div>
            <button type="button" @click="save" :disabled="!canSave" class="btn-primary px-6">
                <span x-show="!saving">Save Batch (Prepare Transfer)</span>
                <span x-show="saving">Saving batch...</span>
            </button>
        </div>
    </div>
</div>
