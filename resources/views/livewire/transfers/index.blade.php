<div class="space-y-3">
    <a href="{{ route('transfers.create', $branch ? ['branch' => $branch] : []) }}" wire:navigate class="btn-primary w-full">+ Send to Branch</a>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
        <button wire:click="$set('branch', null)" class="chip shrink-0 rounded-full px-3 py-1.5 text-sm font-medium {{ $branch === null ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">All branches</button>
        @foreach ($branches as $b)
            <button wire:click="$set('branch', {{ $b->id }})" class="chip shrink-0 rounded-full px-3 py-1.5 text-sm font-medium {{ $branch === $b->id ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ $b->name }}</button>
        @endforeach
    </div>

    @php $selected = $branch ? $branches->firstWhere('id', $branch) : null; @endphp

    <div class="card flex items-center justify-between bg-teal-50">
        <div>
            <p class="text-xs font-medium text-teal-800">
                Total delivered{{ $selected ? ' · '.$selected->name : '' }}
            </p>
            <p class="text-xs text-slate-500">{{ $transfers->total() }} {{ Str::plural('transfer', $transfers->total()) }}</p>
        </div>
        <p class="text-xl font-bold text-teal-800">{{ money($totalValue) }}</p>
    </div>
    @if ($selected)
        <a href="{{ route('branches.show', $selected) }}" wire:navigate class="text-link -mt-1 block text-right text-sm">{{ $selected->name }}: items & delivery report →</a>
    @endif

    <div class="card divide-y divide-slate-100 p-0">
        @forelse ($transfers as $t)
            <a href="{{ route('transfers.show', $t) }}" wire:navigate wire:key="t-{{ $t->id }}" class="row-link flex items-center justify-between gap-3 px-4 py-3 first:rounded-t-2xl last:rounded-b-2xl">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ $t->branch->name }}</p>
                    <p class="text-xs text-slate-500">Transfer #{{ $t->invoice_no }} · {{ $t->transfer_date->format('D, d M Y') }} · {{ $t->lines_count }} {{ Str::plural('item', $t->lines_count) }}</p>
                </div>
                <span class="shrink-0 font-semibold text-slate-900">{{ money($t->total_value) }}</span>
            </a>
        @empty
            <div class="px-4 py-8 text-center text-sm text-slate-500">
                No transfers recorded yet.
            </div>
        @endforelse
    </div>

    {{ $transfers->links() }}
</div>
