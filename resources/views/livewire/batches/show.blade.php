<div class="space-y-4">
    @php $transfer = $batch->transfer; @endphp

    {{-- Dispatch Status / Action Banner --}}
    @if ($transfer)
        <div class="rounded-2xl border-2 border-emerald-500 bg-emerald-50 p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white font-bold text-lg">✓</span>
                    <div>
                        <p class="font-bold text-emerald-950">Dispatched to {{ $transfer->branch->name }}</p>
                        <p class="text-xs text-emerald-700">Invoice #{{ $transfer->invoice_no }} · Sent on {{ $transfer->transfer_date->format('d M Y') }} · Delivery Amount: <span class="font-bold">{{ money($transfer->total_value) }}</span></p>
                    </div>
                </div>
                <a href="{{ route('transfers.show', $transfer) }}" wire:navigate class="rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white shadow hover:bg-emerald-800 text-center">
                    View Invoice #{{ $transfer->invoice_no }} →
                </a>
            </div>
        </div>
    @else
        <div class="rounded-2xl border-2 border-teal-500 bg-teal-50/90 p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-white font-bold text-lg">🚚</span>
                    <div>
                        <p class="font-bold text-teal-950">Ready to Send to Branch</p>
                        <p class="text-xs text-teal-800">Batch prepared from warehouse stock. Click below to select the branch and generate the delivery invoice.</p>
                    </div>
                </div>
                <a href="{{ route('transfers.create', ['from_batch' => $batch->id]) }}" wire:navigate class="btn-primary shrink-0 flex items-center justify-center gap-2 px-5 py-3 text-sm font-bold shadow-md">
                    <span>🚚 Send to Branch</span>
                </a>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="flex items-start justify-between">
            <div class="min-w-0 pr-3">
                <span class="inline-block rounded-md bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-800">Batch</span>
                <h2 class="mt-1 text-xl font-bold text-slate-900 truncate">{{ $batch->batch_no }}</h2>
                <p class="text-sm text-slate-500">{{ $batch->batch_date->format('l, d M Y') }}</p>
            </div>
            <div class="text-right shrink-0">
                <p class="text-xs text-slate-500">Total batch cost</p>
                <p class="text-xl font-bold text-slate-900">{{ money($batch->total_cost) }}</p>
                <p class="text-xs text-slate-400">{{ $lines->count() }} {{ Str::plural('item', $lines->count()) }} used</p>
            </div>
        </div>
        @if ($batch->notes)
            <div class="mt-3 rounded-xl bg-slate-50 p-2.5 text-sm text-slate-700">
                <p class="text-xs font-semibold text-slate-500">Notes:</p>
                <p class="mt-0.5">{{ $batch->notes }}</p>
            </div>
        @endif
    </div>

    <div class="space-y-2">
        <div class="flex items-center justify-between px-1">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Items Used From Stock</h3>
            <span class="text-xs text-slate-400">{{ $lines->count() }} items</span>
        </div>

        <div class="card divide-y divide-slate-100 p-0">
            <div class="grid grid-cols-12 gap-2 bg-slate-50/75 py-2 pl-4 pr-4 text-xs font-semibold text-slate-500">
                <span class="col-span-6">Item & Category</span>
                <span class="col-span-3 text-right">Qty Used</span>
                <span class="col-span-3 text-right">Value</span>
            </div>
            @foreach ($lines as $line)
                <a href="{{ route('items.show', $line->item_id) }}" wire:navigate class="row-link grid grid-cols-12 items-center gap-2 px-4 py-3 text-sm">
                    <div class="col-span-6 min-w-0">
                        <p class="font-semibold text-slate-900 truncate">{{ $line->item->name }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ $line->item->category->name }}</p>
                    </div>
                    <div class="col-span-3 text-right">
                        <span class="font-bold text-teal-800">{{ qty($line->displayQty()) }}</span>
                        <span class="text-xs text-slate-500">{{ $line->displayUnit() }}</span>
                        @if ($line->entered_qty !== null && $line->displayUnit() === 'g' && $line->item->isKg())
                            <span class="block text-[11px] text-slate-400">({{ qty($line->qty) }} kg)</span>
                        @endif
                    </div>
                    <div class="col-span-3 text-right">
                        <span class="font-semibold text-slate-800">{{ money($line->total) }}</span>
                        @if ($line->unit_cost > 0)
                            <span class="block text-[11px] text-slate-400">{{ money($line->unit_cost) }}/{{ $line->item->unit }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <div class="space-y-2 pt-2">
        @unless ($transfer)
            <a href="{{ route('transfers.create', ['from_batch' => $batch->id]) }}" wire:navigate class="btn-primary w-full flex items-center justify-center gap-2 py-3.5 text-base font-bold shadow-md">
                <span>🚚 Send to Branch & Create Invoice</span>
            </a>
        @endunless

        <button wire:click="delete" wire:confirm="Delete this batch? The used items will be returned to warehouse stock." class="btn-light w-full text-xs text-red-600 hover:bg-red-50">
            Delete batch (return items to stock)
        </button>
    </div>
</div>
