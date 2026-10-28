<div class="space-y-4">
    {{-- Branch info + quick transfer button --}}
    <div class="card">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="eyebrow">Total stock delivered (All time)</p>
                <p class="mt-0.5 text-3xl font-bold tracking-tight text-slate-900">{{ money($totalValueAllTime) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $totalTransfersAllTime }} {{ Str::plural('transfer', $totalTransfersAllTime) }} completed</p>
            </div>
            @if ($branch->contact_person || $branch->address || $branch->phone)
                <div class="max-w-[45%] text-right text-xs text-slate-500">
                    @if ($branch->contact_person) <p class="font-medium text-slate-700">ATTN: {{ $branch->contact_person }}</p> @endif
                    @if ($branch->address) <p>{{ $branch->address }}</p> @endif
                    @if ($branch->phone) <a href="tel:{{ $branch->phone }}" class="text-link">{{ $branch->phone }}</a> @endif
                </div>
            @endif
        </div>

        <div class="mt-4">
            <a href="{{ route('transfers.create', ['branch' => $branch->id]) }}" wire:navigate class="btn-primary w-full flex items-center justify-center gap-2">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Send Stock to {{ $branch->name }}
            </a>
        </div>
    </div>

    {{-- Period filter --}}
    <div class="segmented">
        @foreach ($periods as $key => $label)
            <button wire:click="$set('period', '{{ $key }}')" class="{{ $period === $key ? 'is-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Hero delivered in period --}}
    <div class="hero">
        <p class="hero-label">Delivered{{ $from ? ' · '.$from->format('d M').' – '.$to->format('d M Y') : ' · all time' }}</p>
        <p class="hero-value">{{ money($totalValue) }}</p>
        <p class="hero-hint">{{ $transferCount }} {{ Str::plural('transfer', $transferCount) }} · {{ $items->count() }} different {{ Str::plural('item', $items->count()) }}</p>
    </div>

    {{-- Items delivered: kya maal gaya aur kitna gaya --}}
    <div class="card p-0">
        <h2 class="card-title px-4 pb-1 pt-4">Items delivered</h2>
        @if ($items->isEmpty())
            <p class="px-4 pb-4 text-sm text-slate-500">Nothing was sent to this branch in this period.</p>
        @else
            <div class="grid grid-cols-12 gap-2 border-b border-slate-100 py-2 pl-4 pr-9 text-xs font-semibold uppercase tracking-wide text-slate-400">
                <span class="col-span-5">Item</span>
                <span class="col-span-3 text-right">Quantity</span>
                <span class="col-span-4 text-right">Total value</span>
            </div>
            @foreach ($items as $row)
                <a href="{{ route('items.show', $row->id) }}" wire:navigate wire:key="bi-{{ $row->id }}" class="row-link grid grid-cols-12 items-center gap-2 border-b border-slate-100 px-4 py-3 text-xs sm:text-sm">
                    <span class="col-span-5 font-medium truncate">{{ $row->name }}</span>
                    <span class="col-span-3 text-right font-semibold">{{ qty($row->qty) }} <span class="text-xs text-slate-500 font-normal">{{ $row->unit }}</span></span>
                    <span class="col-span-4 text-right font-semibold text-slate-900">{{ money($row->total) }}</span>
                </a>
            @endforeach
            <div class="flex justify-between rounded-b-2xl bg-teal-50 py-3 pl-4 pr-9 text-sm font-bold text-teal-800">
                <span>Total Delivered</span>
                <span>{{ money($totalValue) }}</span>
            </div>
        @endif
    </div>

    {{-- Recent transfers --}}
    <div class="card">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="card-title">Transfers history</h2>
            <a href="{{ route('transfers.index', ['branch' => $branch->id]) }}" wire:navigate class="text-link text-sm">View all →</a>
        </div>
        @forelse ($recentTransfers as $t)
            <a href="{{ route('transfers.show', $t) }}" wire:navigate wire:key="bt-{{ $t->id }}" class="row-link -mx-4 flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <div class="min-w-0">
                    <p class="font-medium">{{ $t->transfer_date->format('D, d M Y') }}</p>
                    <p class="text-xs text-slate-500">Transfer #{{ $t->invoice_no }} · {{ $t->lines_count }} {{ Str::plural('item', $t->lines_count) }}</p>
                </div>
                <span class="font-semibold text-slate-900">{{ money($t->total_value) }}</span>
            </a>
        @empty
            <p class="py-2 text-sm text-slate-500">No transfers in this period.</p>
        @endforelse
    </div>
</div>
