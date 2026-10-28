<div class="space-y-4 pb-20">
    {{-- Demand Header --}}
    <div class="card">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <span class="inline-block rounded-md px-2 py-0.5 text-xs font-bold {{ $demand->statusBadgeClass() }}">
                    {{ $demand->statusLabel() }}
                </span>
                <h2 class="mt-1 text-2xl font-black text-slate-900 tracking-tight">{{ $demand->demand_no }}</h2>
                <p class="text-xs text-slate-500">Demanded by:</p>
                <a href="{{ route('branches.show', $demand->branch) }}" wire:navigate class="text-link text-base font-bold">
                    {{ $demand->branch->name }} ›
                </a>
                @if ($demand->branch->contact_person)
                    <p class="text-xs text-slate-500">ATTN: {{ $demand->branch->contact_person }}</p>
                @endif
            </div>

            <div class="text-right shrink-0">
                <p class="text-xs text-slate-500">Date Received</p>
                <p class="text-sm font-bold text-slate-900">{{ $demand->demand_date->format('d M Y') }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ $lines->count() }} {{ Str::plural('item', $lines->count()) }} requested</p>
            </div>
        </div>

        @if ($demand->notes)
            <div class="mt-3 rounded-xl bg-slate-50 p-2.5 text-xs text-slate-700">
                <span class="font-bold text-slate-500">Notes from branch:</span>
                <p class="mt-0.5 text-slate-800">{{ $demand->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Tareeqa A Workflow Step Progress --}}
    <div class="card p-3.5 bg-gradient-to-r from-teal-50/70 via-slate-50 to-teal-50/40 border border-teal-100">
        <p class="text-[11px] font-bold uppercase tracking-wider text-teal-800 mb-2.5">Tareeqa A: Workflow Progress</p>
        <div class="grid grid-cols-3 gap-2 text-center text-xs">
            {{-- Step 1 --}}
            <div class="rounded-xl p-2 bg-emerald-100/90 text-emerald-900 font-medium">
                <div class="flex items-center justify-center gap-1 text-[11px] font-bold text-emerald-800">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Step 1
                </div>
                <p class="text-xs font-bold mt-0.5">Demand</p>
                <p class="text-[10px] text-emerald-700">Received</p>
            </div>

            {{-- Step 2 --}}
            <div class="rounded-xl p-2 {{ $demand->batch ? 'bg-emerald-100/90 text-emerald-900' : ($demand->isInPreparation() ? 'bg-sky-100 text-sky-900 ring-2 ring-sky-400' : 'bg-slate-100 text-slate-600') }}">
                <div class="flex items-center justify-center gap-1 text-[11px] font-bold">
                    @if ($demand->batch)
                        <svg class="h-3.5 w-3.5 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @endif
                    Step 2
                </div>
                <p class="text-xs font-bold mt-0.5">Batch Prep</p>
                <p class="text-[10px] text-slate-500">{{ $demand->batch ? $demand->batch->batch_no : 'In Warehouse' }}</p>
            </div>

            {{-- Step 3 --}}
            <div class="rounded-xl p-2 {{ $demand->transfer ? 'bg-emerald-100/90 text-emerald-900' : ($demand->isCompleted() ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-600') }}">
                <div class="flex items-center justify-center gap-1 text-[11px] font-bold">
                    @if ($demand->transfer)
                        <svg class="h-3.5 w-3.5 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @endif
                    Step 3
                </div>
                <p class="text-xs font-bold mt-0.5">Transfer</p>
                <p class="text-[10px] text-slate-500">{{ $demand->transfer ? 'Inv #' . $demand->transfer->invoice_no : 'To Branch' }}</p>
            </div>
        </div>
    </div>

    {{-- Action Buttons for Tareeqa A --}}
    @if (! $demand->isCancelled())
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            {{-- Action 1: Create Batch --}}
            @if ($demand->batch)
                <a href="{{ route('batches.show', $demand->batch) }}" wire:navigate class="card p-3 flex items-center justify-between hover:ring-2 hover:ring-teal-400">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-teal-700">Linked Batch</span>
                        <p class="font-bold text-slate-900">{{ $demand->batch->batch_no }}</p>
                        <p class="text-xs text-slate-500">{{ money($demand->batch->total_cost) }} batch cost</p>
                    </div>
                    <span class="btn-light text-xs px-2.5 py-1">View Batch ›</span>
                </a>
            @else
                <a href="{{ route('batches.create', ['demand_id' => $demand->id]) }}" wire:navigate class="card p-3 flex items-center justify-between bg-teal-50/70 border-teal-200 hover:bg-teal-100/80 transition">
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-bold text-teal-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            Step 2: Prepare Batch
                        </div>
                        <p class="text-xs text-teal-700 mt-0.5">Use raw ingredients to prepare requested items</p>
                    </div>
                    <span class="btn-primary text-xs shrink-0 py-1.5 px-3">Start Batch</span>
                </a>
            @endif

            {{-- Action 2: Send Transfer / Invoice --}}
            @if ($demand->transfer)
                <a href="{{ route('transfers.show', $demand->transfer) }}" wire:navigate class="card p-3 flex items-center justify-between hover:ring-2 hover:ring-sky-400">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-sky-700">Linked Invoice</span>
                        <p class="font-bold text-slate-900">Invoice #{{ $demand->transfer->invoice_no }}</p>
                        <p class="text-xs text-slate-500">{{ money($demand->transfer->total_value) }} dispatched</p>
                    </div>
                    <span class="btn-light text-xs px-2.5 py-1">View Invoice ›</span>
                </a>
            @else
                <a href="{{ route('transfers.create', ['demand_id' => $demand->id]) }}" wire:navigate class="card p-3 flex items-center justify-between bg-sky-50/70 border-sky-200 hover:bg-sky-100/80 transition">
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-bold text-sky-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17h6M9 13h6M9 9h1m-4 12h12a2 2 0 002-2V7.4a2 2 0 00-.6-1.4l-3.4-3.4a2 2 0 00-1.4-.6H6a2 2 0 00-2 2v15a2 2 0 002 2z"/></svg>
                            Step 3: Transfer to Branch
                        </div>
                        <p class="text-xs text-sky-700 mt-0.5">Send invoice & dispatch stock to {{ $demand->branch->name }}</p>
                    </div>
                    <span class="btn-primary text-xs shrink-0 py-1.5 px-3">Transfer</span>
                </a>
            @endif
        </div>
    @endif

    {{-- Items List --}}
    <div class="space-y-2">
        <div class="flex items-center justify-between px-1">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Demanded Items</h3>
            <span class="text-xs text-slate-400">{{ $lines->count() }} items</span>
        </div>

        <div class="card divide-y divide-slate-100 p-0">
            <div class="grid grid-cols-12 gap-2 bg-slate-50/75 py-2 px-4 text-xs font-semibold text-slate-500">
                <span class="col-span-6">Item & Category</span>
                <span class="col-span-3 text-right">Requested Qty</span>
                <span class="col-span-3 text-right">Warehouse Stock</span>
            </div>

            @foreach ($lines as $line)
                @php
                    $stock = (float) $line->item->stock_qty;
                    $demanded = (float) $line->qty;
                    $hasEnough = $stock >= $demanded;
                @endphp
                <div class="grid grid-cols-12 items-center gap-2 px-4 py-3 text-sm">
                    <div class="col-span-6 min-w-0">
                        <a href="{{ route('items.show', $line->item_id) }}" wire:navigate class="font-semibold text-slate-900 hover:underline truncate block">
                            {{ $line->item->name }}
                        </a>
                        <p class="text-xs text-slate-400 truncate">{{ $line->item->category->name }}</p>
                        @if ($line->notes)
                            <p class="text-xs text-amber-700 mt-0.5 italic">"{{ $line->notes }}"</p>
                        @endif
                    </div>

                    <div class="col-span-3 text-right">
                        <span class="font-bold text-teal-800">{{ qty($line->qty) }}</span>
                        <span class="text-xs text-slate-500">{{ $line->displayUnit() }}</span>
                    </div>

                    <div class="col-span-3 text-right">
                        <span class="font-semibold text-xs {{ $hasEnough ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ qty($stock) }} {{ $line->item->unit }}
                        </span>
                        @if ($hasEnough)
                            <span class="block text-[10px] text-emerald-600 font-medium">Ready</span>
                        @else
                            <span class="block text-[10px] text-amber-600 font-bold">Needs Prep/Batch</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Bottom options --}}
    <div class="pt-2 flex flex-col gap-2">
        @if ($demand->isPending() || $demand->isInPreparation())
            <button wire:click="cancel" wire:confirm="Cancel this demand?" class="btn-light w-full text-slate-600 hover:bg-slate-100">
                Cancel Demand
            </button>
        @endif

        <button wire:click="delete" wire:confirm="Delete this demand permanently?" class="btn-light w-full text-red-600 hover:bg-red-50">
            Delete Demand
        </button>
    </div>
</div>
