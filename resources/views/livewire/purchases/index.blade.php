<div class="space-y-3">
    <a href="{{ route('purchases.create') }}" wire:navigate class="btn-primary w-full">+ New Purchase</a>

    <div class="card divide-y divide-slate-100 p-0">
        @forelse ($purchases as $p)
            <a href="{{ route('purchases.show', $p) }}" wire:navigate wire:key="p-{{ $p->id }}" class="row-link flex items-center justify-between px-4 py-3">
                <div>
                    <p class="font-medium">{{ $p->purchase_date->format('D, d M Y') }}</p>
                    <p class="text-xs text-slate-500">#{{ $p->id }} · {{ $p->store_name ?: 'Purchase' }} · {{ $p->lines_count }} {{ Str::plural('item', $p->lines_count) }}</p>
                </div>
                <span class="font-semibold">{{ money($p->total) }}</span>
            </a>
        @empty
            <p class="px-4 py-6 text-center text-sm text-slate-500">No purchases yet.</p>
        @endforelse
    </div>

    {{ $purchases->links() }}
</div>
