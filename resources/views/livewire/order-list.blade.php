@php
    $list = $rows->map(fn ($r) => [
        'id' => $r->item->id, 'name' => $r->item->name, 'unit' => $r->item->unit, 'category' => $r->item->category->name,
        'weekly' => $r->weekly_use, 'stock' => $r->stock, 'minimum' => $r->minimum,
        'qty' => $r->suggested > 0 ? (float) $r->suggested : '', 'price' => $r->price, 'pick' => $r->suggested > 0,
    ])->values()->all();
@endphp

<div class="space-y-4 pb-24" wire:key="order-{{ $basis }}"
     x-data="{
        rows: @js($list),
        num(v) { const n = parseFloat(v); return Number.isFinite(n) ? n : 0 },
        money(v) { return 'Rs ' + Math.round(v).toLocaleString('en-PK') },
        qty(v) { return Number(v).toLocaleString('en-PK', { maximumFractionDigits: 3 }) },
        get picked() { return this.rows.filter(r => r.pick && this.num(r.qty) > 0) },
        get total() { return this.picked.reduce((s, r) => s + this.num(r.qty) * r.price, 0) },
        start() {
            const rows = this.picked.map(r => ({ item_id: r.id, qty: String(r.qty), price: r.price || '', pieces: '', description: '' }));
            try {
                localStorage.setItem('purchase-draft-v2', JSON.stringify({ rows, meta: { store_name: '', notes: 'From weekly order list' } }));
            } catch (e) {}
            Livewire.navigate('{{ route('purchases.create') }}');
        },
     }">

    <div class="card space-y-3">
        <div>
            <p class="card-title">What to buy this week</p>
            <p class="text-sm text-slate-500">Based on what went to branches and was lost, plus each item's minimum stock, minus what is in the warehouse now.</p>
        </div>
        <div class="segmented">
            @foreach ($bases as $key => $label)
                <button wire:click="$set('basis', '{{ $key }}')" class="{{ $basis === $key ? 'is-active' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
        <p class="text-xs text-slate-500">Usage from {{ $from->format('d M') }} to {{ $to->format('d M Y') }}{{ $basis === 'avg4' ? ', divided by 4' : '' }}.</p>
    </div>

    @if ($rows->isEmpty())
        <div class="empty-state">
            <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <p class="font-medium text-slate-700">Nothing to suggest yet</p>
            <p>Once stock goes to branches for a week (or you set minimum stock on items), the list fills itself.</p>
        </div>
    @else
        <div class="card p-0">
            <template x-for="(row, i) in rows" :key="row.id">
                <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0" :class="row.pick ? '' : 'opacity-60'">
                    <input type="checkbox" x-model="row.pick" class="h-5 w-5 shrink-0 cursor-pointer rounded border-slate-300 accent-teal-700" :aria-label="'Buy ' + row.name">
                    <div class="min-w-0 flex-1">
                        <p class="line-clamp-2 text-sm font-medium leading-snug" x-text="row.name"></p>
                        <p class="text-xs text-slate-500">
                            Uses <b x-text="qty(row.weekly)"></b>/week · in stock <b x-text="qty(row.stock)"></b>
                            <template x-if="row.minimum > 0"><span> · min <span x-text="qty(row.minimum)"></span></span></template>
                        </p>
                    </div>
                    {{-- Unit sits beside the box, not inside it, so long units like "bottle" never cover the number --}}
                    <div class="flex shrink-0 items-center gap-1.5">
                        <input x-model="row.qty" @input="row.pick = num(row.qty) > 0" inputmode="decimal" class="input w-20 px-2 text-right" placeholder="0" :aria-label="'Quantity of ' + row.name">
                        <span class="w-11 truncate text-xs font-medium text-slate-500" x-text="row.unit"></span>
                    </div>
                </div>
            </template>
        </div>
        <p class="px-1 text-xs text-slate-500">Prices are estimates from the last purchase. You can change everything on the purchase screen.</p>
    @endif

    <div class="action-bar">
        <div>
            <p class="text-xs font-medium text-slate-500"><span x-text="picked.length + (picked.length === 1 ? ' item' : ' items')"></span> · estimated</p>
            <p class="text-xl font-bold tracking-tight" x-text="money(total)"></p>
        </div>
        <button type="button" @click="start" :disabled="!picked.length" class="btn-primary px-5">Start purchase</button>
    </div>
</div>
