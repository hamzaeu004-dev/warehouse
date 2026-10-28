<div class="space-y-4">
    @error('adjustment') <div class="notice-error">{{ $message }}</div> @enderror

    <div class="card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-lg font-bold">{{ $adjustment->reasonLabel() }}</p>
                <p class="text-sm text-slate-500">{{ $adjustment->adjustment_date->format('D, d M Y') }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-500">{{ $adjustment->total_value < 0 ? 'Loss' : 'Value' }}</p>
                <p class="text-xl font-bold {{ $adjustment->total_value < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ money($adjustment->total_value) }}</p>
            </div>
        </div>
        @if ($adjustment->notes)
            <p class="mt-2 rounded-lg bg-slate-50 p-2 text-sm">{{ $adjustment->notes }}</p>
        @endif
    </div>

    <div class="card p-0">
        <div class="grid grid-cols-12 gap-2 border-b border-slate-100 py-2 pl-4 pr-9 text-xs font-medium text-slate-500">
            <span class="col-span-5">Item</span>
            <span class="col-span-4 text-right">{{ $adjustment->isCount() ? 'System → Counted' : 'Removed' }}</span>
            <span class="col-span-3 text-right">Value</span>
        </div>
        @foreach ($lines as $line)
            <a href="{{ route('items.show', $line->item_id) }}" wire:navigate class="row-link grid grid-cols-12 gap-2 border-b border-slate-100 px-4 py-2.5 text-sm last:border-0">
                <span class="col-span-5 font-medium">{{ $line->item->name }}</span>
                <span class="col-span-4 text-right">
                    @if ($adjustment->isCount())
                        {{ qty($line->system_qty) }} → {{ qty($line->counted_qty) }}
                    @else
                        {{ qty(abs($line->qty_change)) }}
                    @endif
                    <span class="text-xs text-slate-500">{{ $line->item->unit }}</span>
                </span>
                <span class="col-span-3 text-right font-semibold {{ $line->total < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ money($line->total) }}</span>
            </a>
        @endforeach
    </div>

    <button wire:click="delete" wire:confirm="Delete this adjustment? Stock will go back to what it was before." class="btn-light w-full text-red-600">Delete adjustment</button>
</div>
