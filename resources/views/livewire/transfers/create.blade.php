<div x-data="stockForm({ items: @js($items), mode: 'transfer', draftKey: @js($isEditing || $fromBatch ? null : 'transfer-draft-v2'), fields: ['transfer_date', 'due_days', 'po_no', 'notes'], initialRows: @js($initialRows) })" class="space-y-4 pb-24">
    @if ($isEditing)
        <div class="notice notice-info">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z"/></svg>
            <span>You are correcting this transfer. Only the difference in quantities changes warehouse stock.</span>
        </div>
    @endif

    @if ($fromBatch)
        <div class="rounded-2xl border-2 border-teal-500 bg-teal-50/90 p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-lg shadow-sm">🚚</span>
                    <div>
                        <p class="font-bold text-teal-950">Dispatching Batch #{{ $fromBatch->batch_no }}</p>
                        <p class="mt-0.5 text-xs text-teal-800">Prepared on {{ $fromBatch->batch_date->format('d M Y') }} · {{ $fromBatch->lines->count() }} items loaded. Select the ordering branch below and click Send.</p>
                    </div>
                </div>
                <a href="{{ route('transfers.create') }}" wire:navigate class="shrink-0 text-xs font-semibold text-slate-500 hover:text-slate-800 underline">Clear batch</a>
            </div>
        </div>
    @elseif (! $isEditing && $availableBatches->isNotEmpty())
        <div class="rounded-xl border border-teal-200 bg-teal-50/50 p-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <p class="text-xs font-bold text-teal-900">📦 Load items from a prepared Batch:</p>
                    <p class="text-[11px] text-teal-700">Quickly send an unsent batch without manually retyping items.</p>
                </div>
                <select onchange="if(this.value) window.location.href='{{ route('transfers.create') }}?from_batch=' + this.value" class="input w-full sm:w-auto text-xs py-1.5 px-3">
                    <option value="">-- Select Ready Batch --</option>
                    @foreach ($availableBatches as $ab)
                        <option value="{{ $ab->id }}">{{ $ab->batch_no }} ({{ $ab->batch_date->format('d M') }} · {{ money($ab->total_cost) }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <div class="card space-y-3">
        <div>
            <label class="label text-base font-bold text-slate-900">1. Select Destination Branch (Order From)</label>
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
            @error('branch_id') <p class="mt-1 text-sm text-red-600 font-semibold">{{ $message }}</p> @enderror

            @php $selected = $branches->firstWhere('id', $branch_id); @endphp
            @if ($selected)
                <div class="mt-2 rounded-lg bg-teal-50/70 border border-teal-100 px-3 py-2 text-xs text-teal-900">
                    <p class="font-bold">Delivering to: {{ $selected->name }}</p>
                    @if ($selected->contact_person) <p><span class="font-semibold text-slate-600">ATTN:</span> {{ $selected->contact_person }}</p> @endif
                    @if ($selected->phone) <p><span class="font-semibold text-slate-600">Phone:</span> {{ $selected->phone }}</p> @endif
                    @if ($selected->address) <p class="text-slate-600">{{ $selected->address }}</p> @endif
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label">Transfer #</label>
                <input wire:model="invoice_no" inputmode="numeric" class="input">
                @error('invoice_no') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Date</label>
                <input wire:model="transfer_date" type="date" max="{{ now()->toDateString() }}" class="input">
                @error('transfer_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
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
                <div class="card space-y-2 p-3" :class="overStock(row) ? 'ring-2 ring-red-400' : ''">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium" x-text="row.name"></p>
                            <p class="text-xs" :class="overStock(row) ? 'text-red-600 font-semibold' : 'text-slate-500'">
                                In warehouse: <span x-text="qty(row.stock)"></span> <span x-text="row.unit"></span>
                            </p>
                        </div>
                        <button type="button" @click="remove(index)" class="-mr-1 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-red-600 active:bg-slate-200" aria-label="Remove">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="grid gap-2" :class="row.unit === 'kg' ? 'grid-cols-3' : 'grid-cols-2'">
                        <div>
                            <label class="text-xs text-slate-500" x-text="row.unit === 'kg' ? 'Kgs' : 'Qty (' + row.unit + ')'"></label>
                            <input :id="`qty-${row.key}`" x-model="row.qty" inputmode="decimal" class="input px-2" placeholder="0">
                        </div>
                        <template x-if="row.unit === 'kg'">
                            <div>
                                <label class="text-xs text-slate-500">Qty (pcs)</label>
                                <input x-model="row.pieces" inputmode="decimal" class="input px-2" placeholder="0">
                            </div>
                        </template>
                        <div>
                            <label class="text-xs text-slate-500">Rate / <span x-text="row.unit"></span></label>
                            <input x-model="row.price" inputmode="decimal" class="input px-2" placeholder="0">
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <button type="button" @click="row.showDescription = !row.showDescription" class="text-link"
                                x-text="row.description ? 'Edit description' : '+ Description'"></button>
                        <p class="text-slate-500">
                            <span x-text="qty(num(row.qty))"></span> × <span x-text="money(num(row.price))"></span> =
                            <span class="text-sm font-bold text-slate-800" x-text="money(lineTotal(row))"></span>
                        </p>
                    </div>
                    <template x-if="row.price !== '' && row.cost > 0 && num(row.price) <= row.cost">
                        <p class="rounded-lg bg-amber-50 px-2 py-1 text-xs text-amber-800">
                            ⚠ Rate is <span x-text="num(row.price) < row.cost ? 'below' : 'equal to'"></span> the purchase cost
                            (<span x-text="money(row.cost)"></span>/<span x-text="row.unit"></span>), so this item makes no profit.
                        </p>
                    </template>
                    <template x-if="row.showDescription">
                        <input x-model="row.description" maxlength="150" class="input px-2 text-sm" placeholder="Description on the invoice (optional)">
                    </template>

                    <template x-if="overStock(row)">
                        <p class="text-xs text-red-600">Not enough stock in the warehouse.</p>
                    </template>
                </div>
            </template>
        </div>
    </template>

    <div class="card">
        <label class="label">Notes (optional, not printed)</label>
        <textarea wire:model="notes" rows="2" class="input" placeholder="Anything to remember"></textarea>
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
            <p class="text-xs font-medium text-slate-500">
                Amount
                <template x-if="totalKgs > 0"><span>· <span x-text="qty(totalKgs)"></span> kg</span></template>
                <template x-if="totalPieces > 0"><span>· <span x-text="qty(totalPieces)"></span> pcs</span></template>
            </p>
            <p class="text-xl font-bold tracking-tight" x-text="money(total)"></p>
        </div>
        <button type="button" @click="save" :disabled="!canSave" class="btn-primary px-6">
            <span x-show="!saving">{{ $isEditing ? 'Save changes' : ($fromBatch ? '🚚 Send Batch & Create Invoice' : '🚚 Confirm & Send to Branch') }}</span>
            <span x-show="saving">Sending…</span>
        </button>
    </div>
</div>
