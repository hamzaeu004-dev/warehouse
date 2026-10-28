<div class="space-y-4">
    @error('delete') <div class="notice notice-error">{{ $message }}</div> @enderror

    @unless ($item->is_active)
        <div class="notice notice-muted">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 4.6A9.9 9.9 0 0112 4.5c6 0 9.75 7.5 9.75 7.5a17 17 0 01-3.2 4.1M6.6 6.6C3.9 8.4 2.25 12 2.25 12s3.75 7.5 9.75 7.5a9.6 9.6 0 004.4-1.1"/></svg>
            <span><b>Hidden item.</b> It is not offered when adding purchases, invoices or adjustments. History below is kept.</span>
        </div>
    @endunless

    <div class="card">
        <p class="eyebrow">{{ $item->category->name }}</p>
        <h2 class="mt-0.5 text-xl font-bold leading-tight {{ $item->is_active ? '' : 'text-slate-500' }}">{{ $item->name }}</h2>

        @php
            $margin = $item->sale_rate !== null && (float) $item->avg_cost > 0
                ? round(((float) $item->sale_rate - (float) $item->avg_cost) / (float) $item->avg_cost * 100, 1) : null;
        @endphp
        <div class="mt-4 grid grid-cols-2 gap-2">
            <div class="stat bg-teal-50">
                <p class="stat-label">In stock</p>
                <p class="stat-value text-teal-700">{{ qty($item->stock_qty) }} <span class="text-sm font-medium">{{ $item->unit }}</span></p>
                <p class="stat-hint">Worth {{ money($item->stock_qty * $item->avg_cost) }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Avg cost</p>
                <p class="stat-value">{{ money($item->avg_cost) }}</p>
                <p class="stat-hint">Last paid {{ $item->last_price !== null ? money($item->last_price) : '–' }}</p>
            </div>
            <div class="stat col-span-2">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="stat-label">Selling rate to branches</p>
                        <p class="stat-value">{{ $item->sale_rate !== null ? money($item->sale_rate).' / '.$item->unit : 'Not set' }}</p>
                    </div>
                    @if ($margin !== null)
                        <span class="badge {{ $margin > 0 ? 'badge-green' : 'badge-red' }}">{{ $margin > 0 ? '+' : '' }}{{ $margin }}% on cost</span>
                    @endif
                </div>
                <p class="stat-hint">
                    @if ($item->sale_rate === null) Invoices use the last rate you charged. Set one in Edit to always start from it. @else New invoices start from this rate. @endif
                    @if ($item->min_stock !== null) · Alert below {{ qty($item->min_stock) }} {{ $item->unit }} @endif
                </p>
            </div>
        </div>

        @unless ($editing)
            <div class="mt-4 grid grid-cols-2 gap-2">
                @if ($item->is_active)
                    <a href="{{ route('adjustments.create', ['item' => $item->id, 'reason' => 'count']) }}" wire:navigate class="btn-light col-span-2">Count / adjust stock</a>
                @endif
                <button wire:click="edit" class="btn-light">Edit</button>
                @if ($item->is_active)
                    <button wire:click="toggleHidden"
                            wire:confirm="Hide {{ $item->name }}? It will no longer show when adding purchases or invoices.{{ (float) $item->stock_qty > 0 ? ' It still has '.qty($item->stock_qty).' '.$item->unit.' in stock.' : '' }} You can show it again any time."
                            class="btn-light">Hide item</button>
                @else
                    <button wire:click="toggleHidden" class="btn-primary">Use again</button>
                @endif
                @unless ($hasHistory)
                    <button wire:click="delete" wire:confirm="Delete this item permanently?" class="btn-light col-span-2 text-red-600">Delete</button>
                @endunless
            </div>
            @if ($hasHistory && $item->is_active)
                <p class="mt-2 text-xs text-slate-500">This item has history, so it can't be deleted. If you no longer buy it, hide it instead.</p>
            @endif
        @endunless
    </div>

    @if ($editing)
        <form wire:submit="save" class="card space-y-3">
            <div>
                <label class="label">Name</label>
                <input wire:model="name" class="input">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
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
                        @foreach (array_unique([...$units, $unit]) as $u)
                            <option value="{{ $u }}">{{ $u }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Selling rate / {{ $unit }}</label>
                    <input wire:model="sale_rate" inputmode="decimal" class="input" placeholder="e.g. 669.20">
                    @error('sale_rate') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Min stock</label>
                    <input wire:model="min_stock" inputmode="decimal" class="input" placeholder="Alert below">
                    @error('min_stock') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-slate-500">Leave a box empty if you don't need it. Average cost now: {{ money($item->avg_cost) }}.</p>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" wire:click="$set('editing', false)" class="btn-light">Cancel</button>
                <button class="btn-primary">Save</button>
            </div>
        </form>
    @endif

    <div class="card">
        <h2 class="card-title mb-1">History</h2>
        @forelse ($history as $h)
            <a href="{{ $h['url'] }}" wire:navigate class="row-link -mx-4 flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <div>
                    <p class="text-sm font-medium">{{ $h['title'] }}</p>
                    <p class="text-xs text-slate-500">{{ $h['date']->format('d M Y') }} · {{ money($h['rate']) }}/{{ $item->unit }}</p>
                </div>
                <span class="font-semibold {{ $h['qty'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $h['qty'] >= 0 ? '+' : '−' }}{{ qty(abs($h['qty'])) }}
                </span>
            </a>
        @empty
            <p class="text-sm text-slate-500">No history yet.</p>
        @endforelse
    </div>
</div>
