<div class="space-y-3">
    <a href="{{ route('adjustments.create') }}" wire:navigate class="btn-primary w-full">+ Adjust Stock</a>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
        <button wire:click="$set('reason', '')" class="chip shrink-0 rounded-full px-3 py-1.5 text-sm font-medium {{ $reason === '' ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">All</button>
        @foreach ($reasons as $key => $label)
            <button wire:click="$set('reason', '{{ $key }}')" class="chip shrink-0 rounded-full px-3 py-1.5 text-sm font-medium {{ $reason === $key ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="card divide-y divide-slate-100 p-0">
        @forelse ($adjustments as $a)
            <a href="{{ route('adjustments.show', $a) }}" wire:navigate wire:key="a-{{ $a->id }}" class="row-link flex items-center justify-between px-4 py-3">
                <div>
                    <p class="font-medium">{{ $a->reasonLabel() }}</p>
                    <p class="text-xs text-slate-500">#{{ $a->id }} · {{ $a->adjustment_date->format('D, d M Y') }} · {{ $a->lines_count }} {{ Str::plural('item', $a->lines_count) }}</p>
                </div>
                <span class="font-semibold {{ $a->total_value < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ money($a->total_value) }}</span>
            </a>
        @empty
            <p class="px-4 py-6 text-center text-sm text-slate-500">No adjustments yet.</p>
        @endforelse
    </div>

    {{ $adjustments->links() }}
</div>
