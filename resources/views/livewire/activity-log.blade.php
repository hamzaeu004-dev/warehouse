@php
    // Icon and colour per kind of activity
    $styles = [
        'purchase' => ['bg-emerald-100 text-emerald-700', 'M12 4v16m8-8H4'],
        'transfer' => ['bg-sky-100 text-sky-700', 'M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4'],
        'adjustment' => ['bg-amber-100 text-amber-700', 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z'],
        'item' => ['bg-slate-200 text-slate-700', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        'payment' => ['bg-emerald-100 text-emerald-700', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
        'category' => ['bg-cyan-100 text-cyan-700', 'M4 6h7v7H4zM13 6h7v7h-7zM4 15h7v5H4zM13 15h7v5h-7z'],
        'branch' => ['bg-violet-100 text-violet-700', 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6'],
        'account' => ['bg-slate-100 text-slate-600', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ];
    $dayLabel = function (string $day) {
        $date = \Illuminate\Support\Carbon::parse($day);
        return match (true) {
            $date->isToday() => 'Today',
            $date->isYesterday() => 'Yesterday',
            default => $date->format('l, d M Y'),
        };
    };
@endphp

<div class="space-y-3">
    <div class="flex gap-2">
        <input wire:model.live.debounce.300ms="search" type="search" class="input" placeholder="Search, e.g. oil, Branch 1, deleted...">
        <input wire:model.live="date" type="date" max="{{ now()->toDateString() }}" class="input w-auto shrink-0 px-2 text-sm" aria-label="Filter by date">
    </div>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
        <button wire:click="$set('group', '')" class="chip shrink-0 rounded-full px-3 py-1.5 text-sm font-medium {{ $group === '' ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">All</button>
        @foreach ($groups as $key => $label)
            <button wire:click="$set('group', '{{ $key }}')" class="chip shrink-0 rounded-full px-3 py-1.5 text-sm font-medium {{ $group === $key ? 'bg-teal-700 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($date !== '' || $search !== '')
        <div class="flex items-center justify-between text-sm">
            <span class="text-slate-500">{{ $total }} {{ Str::plural('result', $total) }}</span>
            <button wire:click="clearFilters" class="text-link">Clear filters</button>
        </div>
    @endif

    @forelse ($days as $day => $logs)
        <div wire:key="day-{{ $day }}">
            <h2 class="sticky top-14 z-10 -mx-4 bg-slate-100/95 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 backdrop-blur">{{ $dayLabel($day) }}</h2>
            <div class="card divide-y divide-slate-100 p-0">
                @foreach ($logs as $log)
                    @php
                        [$color, $icon] = $styles[$log->group()] ?? $styles['account'];
                        if ($log->isDeletion() || $log->isWarning()) $color = 'bg-red-100 text-red-700';
                        $url = $links[$log->id] ?? null;
                        $lines = $log->properties['lines'] ?? [];
                    @endphp
                    <div wire:key="log-{{ $log->id }}" class="px-4 py-3">
                        <div class="flex gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $color }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $log->isDeletion() ? 'M6 7h12M9 7V4h6v3m-7 0l1 13h6l1-13' : $icon }}"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm leading-snug {{ $log->isWarning() ? 'font-medium text-red-700' : 'text-slate-800' }}">{{ $log->description }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $log->created_at->format('g:i a') }}
                                    @if ($log->user) · {{ $log->user->name }} @endif
                                    @if ($url) · <a href="{{ $url }}" wire:navigate class="text-link">Open →</a> @endif
                                </p>

                                @if ($lines)
                                    <details class="group mt-1.5">
                                        <summary class="text-link inline-flex list-none items-center gap-1 text-xs [&::-webkit-details-marker]:hidden">
                                            <svg class="h-3 w-3 transition group-open:rotate-90" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                            <span class="group-open:hidden">Show {{ count($lines) }} {{ Str::plural('item', count($lines)) }}</span>
                                            <span class="hidden group-open:inline">Hide items</span>
                                        </summary>
                                        <div class="mt-1.5 divide-y divide-slate-100 rounded-lg bg-slate-50 text-xs">
                                            @foreach ($lines as $line)
                                                <div class="flex justify-between gap-2 px-2.5 py-1.5">
                                                    <span class="text-slate-700">{{ $line['name'] }}</span>
                                                    <span class="shrink-0 text-right text-slate-500">
                                                        {{ $line['qty'] > 0 && $log->group() === 'adjustment' ? '+' : '' }}{{ qty($line['qty']) }} {{ $line['unit'] }}
                                                        × {{ money($line['rate']) }}
                                                        = <span class="font-medium text-slate-700">{{ money($line['total']) }}</span>
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card py-8 text-center text-sm text-slate-500">No activity found.</div>
    @endforelse

    @if ($total > $shown)
        <button wire:click="showMore" class="btn-light w-full">Show older ({{ $total - $shown }} more)</button>
    @endif
</div>
