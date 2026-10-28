<div class="space-y-4">
    @error('purchase') <div class="notice notice-error">{{ $message }}</div> @enderror

    <div class="card">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="eyebrow">Purchase #{{ $purchase->id }}</p>
                <p class="mt-0.5 text-lg font-bold">{{ $purchase->purchase_date->format('D, d M Y') }}</p>
                <p class="text-sm text-slate-500">{{ $purchase->store_name ?: 'No store name' }}</p>
            </div>
            <div class="shrink-0 text-right">
                <p class="eyebrow">Total</p>
                <p class="mt-0.5 text-xl font-bold text-teal-700">{{ money($purchase->total) }}</p>
            </div>
        </div>
        @if ($purchase->notes)
            <p class="mt-3 rounded-xl bg-slate-50 p-3 text-sm">{{ $purchase->notes }}</p>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-2">
        <a href="{{ route('purchases.edit', $purchase) }}" wire:navigate class="btn-primary">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z"/></svg>
            Edit
        </a>
        <button wire:click="delete" wire:confirm="Delete this purchase? Its stock will also be removed." class="btn-light text-red-600">Delete</button>
    </div>

    <div class="card p-0">
        <div class="grid grid-cols-12 gap-2 border-b border-slate-100 py-2.5 pl-4 pr-9 text-xs font-semibold uppercase tracking-wide text-slate-400">
            <span class="col-span-5">Item</span>
            <span class="col-span-2 text-right">Qty</span>
            <span class="col-span-2 text-right">Price</span>
            <span class="col-span-3 text-right">Total</span>
        </div>
        @foreach ($lines as $line)
            <a href="{{ route('items.show', $line->item_id) }}" wire:navigate class="row-link grid grid-cols-12 gap-2 border-b border-slate-100 px-4 py-3 text-sm">
                <span class="col-span-5 font-medium">{{ $line->item->name }}</span>
                <span class="col-span-2 text-right">
                    {{ qty($line->qty) }} <span class="text-xs text-slate-500">{{ $line->item->unit }}</span>
                    @if ($line->pieces !== null) <span class="block text-xs text-slate-500">{{ qty($line->pieces) }} pcs</span> @endif
                </span>
                <span class="col-span-2 text-right">{{ number_format($line->unit_price, fmod((float) $line->unit_price, 1.0) == 0.0 ? 0 : 2) }}</span>
                <span class="col-span-3 text-right font-semibold">{{ money($line->total) }}</span>
            </a>
        @endforeach
        <div class="flex justify-between rounded-b-2xl bg-teal-50 py-3 pl-4 pr-9 text-sm font-bold text-teal-800">
            <span>Total ({{ $lines->count() }} {{ Str::plural('item', $lines->count()) }})</span>
            <span>{{ money($purchase->total) }}</span>
        </div>
    </div>
</div>
