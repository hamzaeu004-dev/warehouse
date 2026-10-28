<div x-data="stockForm({ items: @js($items), mode: 'purchase', draftKey: @js($isEditing ? null : 'purchase-draft-v2'), fields: ['purchase_date', 'store_name', 'notes'], initialRows: @js($initialRows) })" class="space-y-4 pb-24">
    @if ($isEditing)
        <div class="notice notice-info">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z"/></svg>
            <span>You are correcting purchase #{{ $editingId }}. Stock and average cost are adjusted by the difference only.</span>
        </div>
    @endif

    <div class="card grid grid-cols-2 gap-3">
        <div>
            <label class="label">Date</label>
            <input wire:model="purchase_date" type="date" max="{{ now()->toDateString() }}" class="input">
            @error('purchase_date') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Store</label>
            <input wire:model="store_name" class="input" placeholder="e.g. Metro">
        </div>
    </div>

    @include('livewire.partials.item-picker')

    <template x-if="rows.length">
        <div class="space-y-2">
            <div class="flex items-center justify-between px-1">
                <p class="section-title" x-text="rows.length + (rows.length === 1 ? ' item' : ' items')"></p>
                <button type="button" @click="clearAll" class="text-sm font-medium text-red-600 hover:underline">Clear all</button>
            </div>
            <template x-for="(row, index) in rows" :key="row.key">
                <div class="card space-y-3 p-3.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold leading-tight" x-text="row.name"></p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                In stock <span x-text="qty(row.stock)"></span> <span x-text="row.unit"></span>
                                <template x-if="row.last_price !== null">
                                    <span> · last paid <span x-text="money(row.last_price)"></span></span>
                                </template>
                            </p>
                        </div>
                        <button type="button" @click="remove(index)" class="icon-btn -mr-1 -mt-1 hover:text-red-600" aria-label="Remove">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>
                    <div class="grid gap-2" :class="row.unit === 'kg' ? 'grid-cols-3' : 'grid-cols-2'">
                        <div>
                            <label class="mini-label" x-text="row.unit === 'kg' ? 'Kgs' : 'Qty (' + row.unit + ')'"></label>
                            <input :id="`qty-${row.key}`" x-model="row.qty" inputmode="decimal" class="input px-2.5" placeholder="0">
                        </div>
                        <template x-if="row.unit === 'kg'">
                            <div>
                                <label class="mini-label">Pcs (optional)</label>
                                <input x-model="row.pieces" inputmode="decimal" class="input px-2.5" placeholder="0">
                            </div>
                        </template>
                        <div>
                            <label class="mini-label">Price / <span x-text="row.unit"></span></label>
                            <input x-model="row.price" inputmode="decimal" class="input px-2.5" placeholder="0"
                                   :class="row.last_price !== null && num(row.price) > row.last_price ? '!border-amber-400' : ''">
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span>
                            <template x-if="row.last_price !== null && row.price !== '' && num(row.price) !== row.last_price">
                                <span :class="num(row.price) > row.last_price ? 'text-amber-700' : 'text-emerald-700'"
                                      x-text="(num(row.price) > row.last_price ? '▲ ' : '▼ ') + money(Math.abs(num(row.price) - row.last_price)) + (num(row.price) > row.last_price ? ' costlier' : ' cheaper') + ' than last time'"></span>
                            </template>
                        </span>
                        <span class="shrink-0 text-slate-500">Total <b class="text-sm text-slate-900" x-text="money(lineTotal(row))"></b></span>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <div class="card">
        <label class="label">Notes</label>
        <textarea wire:model="notes" rows="2" class="input" placeholder="Anything to remember (optional)"></textarea>
    </div>

    @if ($errors->any())
        <div class="notice notice-error">
            <div>
                @foreach (array_unique($errors->all()) as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    <div class="action-bar">
        <div>
            <p class="text-xs font-medium text-slate-500">Grand total</p>
            <p class="text-xl font-bold tracking-tight" x-text="money(total)"></p>
        </div>
        <button type="button" @click="save" :disabled="!canSave" class="btn-primary px-6">
            <span x-show="!saving">{{ $isEditing ? 'Save changes' : 'Save purchase' }}</span>
            <span x-show="saving">Saving…</span>
        </button>
    </div>
</div>
