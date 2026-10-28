<div x-data="stockForm({ items: @js($items), mode: 'adjust', draftKey: 'adjust-draft-v2', reason: @js($reason), startItem: @js($startItem), fields: ['adjustment_date', 'notes'] })" class="space-y-4 pb-20">
    <div class="card space-y-3">
        <div>
            <label class="label">Reason</label>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($reasons as $key => $label)
                    <button type="button" @click="setReason(@js($key))"
                            class="rounded-xl border-2 px-2 py-2.5 text-sm font-semibold transition hover:border-teal-400 {{ $key === 'count' ? 'col-span-2' : '' }}"
                            :class="reason === @js($key) ? 'border-teal-700 bg-teal-50 text-teal-800' : 'border-slate-200 text-slate-600'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500" x-show="!isCount">Enter how much was thrown away. Stock goes down.</p>
            <p class="mt-2 text-xs text-slate-500" x-show="isCount" x-cloak>Count the item in the warehouse and enter the real quantity. The system fixes the difference.</p>
            @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Date</label>
            <input wire:model="adjustment_date" type="date" max="{{ now()->toDateString() }}" class="input">
            @error('adjustment_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    @include('livewire.partials.item-picker', ['allowCreate' => false])

    <template x-if="rows.length">
        <div class="space-y-2">
            <div class="flex items-center justify-between px-1">
                <p class="text-sm font-medium text-slate-600"><span x-text="rows.length + (rows.length === 1 ? ' item' : ' items')"></span></p>
                <button type="button" @click="clearAll" class="text-sm text-red-600 hover:underline">Clear all</button>
            </div>
            <template x-for="(row, index) in rows" :key="row.key">
                <div class="card p-3" :class="overStock(row) ? 'ring-2 ring-red-400' : ''">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium" x-text="row.name"></p>
                            <p class="text-xs text-slate-500">
                                System stock: <span x-text="qty(row.stock)"></span> <span x-text="row.unit"></span>
                            </p>
                        </div>
                        <div class="w-28">
                            <label class="text-xs text-slate-500" x-text="isCount ? 'Counted' : 'Remove'"></label>
                            <input :id="`qty-${row.key}`" x-model="row.qty" inputmode="decimal" class="input px-2 text-right" placeholder="0">
                        </div>
                        <button type="button" @click="remove(index)" class="mt-4 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-red-600 active:bg-slate-200" aria-label="Remove">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>
                    <template x-if="overStock(row)">
                        <p class="mt-1 text-xs text-red-600">Cannot remove more than the stock.</p>
                    </template>
                    <template x-if="!overStock(row) && row.qty !== ''">
                        <p class="mt-1 text-xs font-medium"
                           :class="change(row) < 0 ? 'text-red-600' : (change(row) > 0 ? 'text-emerald-600' : 'text-slate-500')"
                           x-text="change(row) === 0
                                ? 'Matches system stock, no change'
                                : (change(row) > 0 ? '+' : '') + qty(change(row)) + ' ' + row.unit + ' · ' + money(lineTotal(row))"></p>
                    </template>
                </div>
            </template>
        </div>
    </template>

    <div class="card">
        <label class="label">Notes <span x-text="reason === 'other' ? '(required)' : '(optional)'"></span></label>
        <textarea wire:model="notes" rows="2" class="input" placeholder="What happened? e.g. oil spilled, rats, found extra in store room"></textarea>
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
                <p class="text-xs text-slate-500" x-text="total < 0 ? 'Stock loss' : 'Stock change'"></p>
                <p class="text-lg font-bold" :class="total < 0 ? 'text-red-600' : ''" x-text="money(total)"></p>
            </div>
            <button type="button" @click="save" :disabled="!canSave" class="btn-primary px-6">
                <span x-show="!saving">Save</span>
                <span x-show="saving">Saving...</span>
            </button>
        </div>
    </div>
</div>
