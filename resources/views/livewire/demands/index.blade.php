<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('demands.create') }}" wire:navigate class="btn-primary w-full sm:w-auto">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            + New Demand
        </a>
    </div>

    {{-- Filter tabs --}}
    <div class="flex overflow-x-auto gap-1.5 pb-1 text-xs">
        <button wire:click="$set('status', '')"
                class="rounded-lg px-3 py-1.5 font-semibold transition shrink-0 {{ $status === '' ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
            All ({{ $counts['all'] }})
        </button>
        <button wire:click="$set('status', 'pending')"
                class="rounded-lg px-3 py-1.5 font-semibold transition shrink-0 flex items-center gap-1.5 {{ $status === 'pending' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 ring-1 ring-amber-200' }}">
            Pending
            @if ($counts['pending'] > 0)
                <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $status === 'pending' ? 'bg-white/25 text-white' : 'bg-amber-200 text-amber-900 font-bold' }}">{{ $counts['pending'] }}</span>
            @endif
        </button>
        <button wire:click="$set('status', 'in_preparation')"
                class="rounded-lg px-3 py-1.5 font-semibold transition shrink-0 flex items-center gap-1.5 {{ $status === 'in_preparation' ? 'bg-sky-600 text-white' : 'bg-sky-50 text-sky-800 hover:bg-sky-100 ring-1 ring-sky-200' }}">
            In Prep
            @if ($counts['in_preparation'] > 0)
                <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $status === 'in_preparation' ? 'bg-white/25 text-white' : 'bg-sky-200 text-sky-900 font-bold' }}">{{ $counts['in_preparation'] }}</span>
            @endif
        </button>
        <button wire:click="$set('status', 'completed')"
                class="rounded-lg px-3 py-1.5 font-semibold transition shrink-0 {{ $status === 'completed' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 ring-1 ring-emerald-200' }}">
            Transferred ({{ $counts['completed'] }})
        </button>
    </div>

    @if ($branches->count() > 1)
        <div class="card p-2.5">
            <label class="text-xs font-semibold text-slate-500 block mb-1">Filter by branch</label>
            <select wire:model.live="branch" class="input py-1 text-sm">
                <option value="">All branches</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="card divide-y divide-slate-100 p-0">
        @forelse ($demands as $d)
            <a href="{{ route('demands.show', $d) }}" wire:navigate wire:key="d-{{ $d->id }}"
               class="row-link flex items-center justify-between px-4 py-3.5 first:rounded-t-2xl last:rounded-b-2xl">
                <div class="min-w-0 pr-3">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-900">{{ $d->demand_no }}</span>
                        <span class="rounded-md px-2 py-0.5 text-[11px] font-bold {{ $d->statusBadgeClass() }}">
                            {{ $d->statusLabel() }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm font-medium text-slate-700 truncate">
                        {{ $d->branch->name }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $d->demand_date->format('D, d M Y') }} · {{ $d->lines_count }} {{ Str::plural('item', $d->lines_count) }} requested
                    </p>
                    @if ($d->batch)
                        <p class="text-[11px] text-teal-700 font-medium mt-0.5">
                            Batch: {{ $d->batch->batch_no }}
                        </p>
                    @endif
                    @if ($d->transfer)
                        <p class="text-[11px] text-sky-700 font-medium mt-0.5">
                            Invoice: #{{ $d->transfer->invoice_no }} ({{ money($d->transfer->total_value) }})
                        </p>
                    @endif
                </div>

                <div class="text-right shrink-0">
                    <span class="btn-light text-xs px-2.5 py-1">View ›</span>
                </div>
            </a>
        @empty
            <div class="px-4 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <p class="mt-2 text-sm font-semibold text-slate-700">No demands found</p>
                <p class="text-xs text-slate-400 mt-0.5">When branches request items, they will appear here</p>
                <a href="{{ route('demands.create') }}" wire:navigate class="btn-primary mt-4 inline-flex items-center text-xs">
                    + Create First Demand
                </a>
            </div>
        @endforelse
    </div>

    {{ $demands->links() }}
</div>
