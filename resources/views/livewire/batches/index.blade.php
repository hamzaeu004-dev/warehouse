<div class="space-y-3">
    <a href="{{ route('batches.create') }}" wire:navigate class="btn-primary w-full">+ New Batch</a>

    <div class="card divide-y divide-slate-100 p-0">
        @forelse ($batches as $b)
            <a href="{{ route('batches.show', $b) }}" wire:navigate wire:key="b-{{ $b->id }}" class="row-link flex items-center justify-between px-4 py-3">
                <div class="min-w-0 pr-3 space-y-1">
                    <div class="flex items-center gap-2">
                        <p class="font-bold text-slate-900 truncate">{{ $b->batch_no }}</p>
                        @if ($b->transfer)
                            <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                                ✓ Sent: {{ $b->transfer->branch->name }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-teal-100 px-2 py-0.5 text-[11px] font-semibold text-teal-800">
                                ⚡ Ready to Send
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500">{{ $b->batch_date->format('D, d M Y') }} · {{ $b->lines_count }} {{ Str::plural('item', $b->lines_count) }} used</p>
                </div>
                <div class="text-right shrink-0">
                    <span class="font-bold text-slate-900">{{ money($b->total_cost) }}</span>
                    @if ($b->transfer)
                        <span class="block text-[11px] text-emerald-700 font-semibold">Inv #{{ $b->transfer->invoice_no }} →</span>
                    @else
                        <span class="block text-[11px] text-teal-700 font-semibold">Send →</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="px-4 py-10 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <p class="mt-2 text-sm font-medium text-slate-600">No batches yet</p>
                <p class="text-xs text-slate-400">Create a batch to record items used from stock</p>
            </div>
        @endforelse
    </div>

    {{ $batches->links() }}
</div>
