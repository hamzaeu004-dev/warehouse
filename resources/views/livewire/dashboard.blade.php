@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $net = $weekProfit['cash_profit'];   // on money the branches paid
    $actions = [
        ['url' => route('purchases.create'), 'label' => 'New purchase', 'hint' => 'Stock arrived', 'color' => 'bg-teal-100 text-teal-700', 'icon' => 'M12 4v16m8-8H4'],
        ['url' => route('batches.create'), 'label' => 'New batch', 'hint' => 'Prepare branch order', 'color' => 'bg-emerald-100 text-emerald-700', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
        ['url' => route('transfers.create'), 'label' => 'Send to branch', 'hint' => 'Create delivery invoice', 'color' => 'bg-sky-100 text-sky-700', 'icon' => 'M9 17h6M9 13h6M9 9h1m-4 12h12a2 2 0 002-2V7.4a2 2 0 00-.6-1.4l-3.4-3.4a2 2 0 00-1.4-.6H6a2 2 0 00-2 2v15a2 2 0 002 2z'],
        ['url' => route('order-list'), 'label' => 'Order list', 'hint' => $orderCount ? "{$orderCount} to buy" : 'What to buy', 'color' => 'bg-violet-100 text-violet-700', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'badge' => $orderCount],
    ];
@endphp

<div class="space-y-4">
    {{-- Greeting + what the warehouse is worth right now --}}
    <div class="hero">
        <svg class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 text-white/10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        <p class="text-sm text-teal-50/90">{{ $greeting }}, {{ auth()->user()->name }}</p>
        <p class="text-xs text-teal-100/80">{{ now()->format('l, d M Y') }}</p>
        <p class="hero-label mt-4">Stock in warehouse</p>
        <p class="hero-value">{{ money($stockValue) }}</p>
        <p class="hero-hint">{{ $itemsInStock }} of {{ $itemsTotal }} items in stock</p>
    </div>

    {{-- Shown until the basics are in place --}}
    @if (! $setup['branches'] || ! $setup['items'] || ! $setup['purchases'])
        @php
            $steps = [
                ['done' => $setup['branches'] > 0, 'label' => 'Add your branches', 'hint' => 'Name, ATTN and address for the invoice', 'url' => route('branches')],
                ['done' => $setup['items'] > 0, 'label' => 'Add your items', 'hint' => 'On the Stock page, or type them while making the first purchase', 'url' => route('items.index')],
                ['done' => $setup['purchases'] > 0, 'label' => 'Enter the first purchase', 'hint' => 'Stock that arrived at the warehouse', 'url' => route('purchases.create')],
            ];
        @endphp
        <div class="card ring-teal-200">
            <h2 class="card-title">Get started</h2>
            <p class="text-xs text-slate-500">Set up the app with your own data.</p>
            <div class="mt-2">
                @foreach ($steps as $i => $step)
                    <a href="{{ $step['url'] }}" wire:navigate class="row-link -mx-4 flex items-center gap-3 border-t border-slate-100 px-4 py-3 first:border-0">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $step['done'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            @if ($step['done'])
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold {{ $step['done'] ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $step['label'] }}</span>
                            <span class="block text-xs text-slate-500">{{ $step['hint'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Main jobs, big enough for a thumb --}}
    <div class="grid grid-cols-2 gap-3">
        @foreach ($actions as $a)
            <a href="{{ $a['url'] }}" wire:navigate class="card relative flex flex-col items-start gap-2.5 p-3.5 transition hover:ring-teal-600/30 active:scale-[0.98]">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $a['color'] }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $a['icon'] }}"/></svg>
                </span>
                <span>
                    <span class="block text-[15px] font-semibold leading-tight text-slate-900">{{ $a['label'] }}</span>
                    <span class="mt-0.5 block text-xs text-slate-500">{{ $a['hint'] }}</span>
                </span>
                @if (! empty($a['badge']))
                    <span class="absolute right-2 top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-violet-600 px-1.5 text-[11px] font-bold text-white">{{ $a['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Stock summary: Purchases vs Dispatched to Branches --}}
    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('analysis') }}" wire:navigate class="card transition hover:ring-teal-600/30">
            <p class="eyebrow">Bought this week</p>
            <p class="mt-1 text-xl font-bold tracking-tight text-teal-700">{{ money($weekPurchases) }}</p>
            <p class="mt-0.5 text-xs text-slate-500">{{ money($monthPurchases) }} this month</p>
            <p class="text-link mt-2 text-xs">Analysis →</p>
        </a>
        <a href="{{ route('transfers.index') }}" wire:navigate class="card transition hover:ring-teal-600/30">
            <p class="eyebrow">Sent to branches</p>
            <p class="mt-1 text-xl font-bold tracking-tight text-sky-800">{{ money($monthDispatched) }}</p>
            <p class="mt-0.5 text-xs text-slate-500">{{ money($weekDispatched) }} this week</p>
            <p class="text-link mt-2 text-xs">Transfers →</p>
        </a>
    </div>

    <div class="card">
        <div class="flex items-center justify-between">
            <div>
                <p class="eyebrow">Bought this week</p>
                <p class="mt-1 text-xl font-bold tracking-tight">{{ money($weekPurchases) }}</p>
            </div>
            <div class="text-right">
                <p class="eyebrow">This month</p>
                <p class="mt-1 text-sm font-semibold text-slate-700">{{ money($monthPurchases) }}</p>
            </div>
        </div>
        @if ($monthLoss < 0)
            <a href="{{ route('adjustments.index') }}" wire:navigate class="row-link -mx-4 -mb-4 mt-3 flex items-center justify-between rounded-b-2xl border-t border-slate-100 bg-red-50/60 px-4 py-2.5 text-sm hover:bg-red-50">
                <span class="text-red-800">Stock lost this month</span>
                <span class="font-semibold text-red-700">{{ money(abs($monthLoss)) }}</span>
            </a>
        @endif
    </div>

    @if ($lowStock->isNotEmpty())
        <div class="card ring-amber-200">
            <div class="mb-1 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="badge-amber">{{ $totalLowStockCount }}</span>
                    <h2 class="card-title">Running low</h2>
                </div>
                <a href="{{ route('order-list') }}" wire:navigate class="text-link text-sm">Order list →</a>
            </div>
            @foreach ($lowStock as $item)
                <a href="{{ route('items.show', $item) }}" wire:navigate class="row-link -mx-4 flex justify-between border-t border-slate-100 px-4 py-2.5 text-sm first-of-type:border-0">
                    <span>{{ $item->name }}</span>
                    <span class="font-semibold text-amber-700">{{ qty($item->stock_qty) }} {{ $item->unit }}</span>
                </a>
            @endforeach
            @if ($totalLowStockCount > 3)
                <a href="{{ route('order-list') }}" wire:navigate class="row-link -mx-4 -mb-4 mt-1 flex items-center justify-between rounded-b-2xl border-t border-slate-100 bg-amber-50/60 px-4 py-2.5 text-xs font-semibold text-amber-800 hover:bg-amber-100/60">
                    <span>View all {{ $totalLowStockCount }} low items in Order list</span>
                    <span>→</span>
                </a>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="card-title">Branches this month</h2>
            <a href="{{ route('branches') }}" wire:navigate class="text-link text-sm">All branches →</a>
        </div>
        @foreach ($branchTotals as $branch)
            <a href="{{ route('branches.show', $branch) }}" wire:navigate class="row-link -mx-4 flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <span class="flex min-w-0 items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600">{{ mb_substr(trim(str_ireplace('ROOSTERS -', '', $branch->name)), 0, 1) }}</span>
                    <span class="text-sm font-medium leading-snug">{{ $branch->name }}</span>
                </span>
                <span class="shrink-0 pl-2 font-semibold">{{ money($branch->month_total ?? 0) }}</span>
            </a>
        @endforeach
        @if ($totalBranchesCount > 3)
            <a href="{{ route('branches') }}" wire:navigate class="row-link -mx-4 -mb-4 mt-1 flex items-center justify-between rounded-b-2xl border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                <span>View all {{ $totalBranchesCount }} branches</span>
                <span>→</span>
            </a>
        @endif
    </div>

    <div class="card">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="card-title">Recent purchases</h2>
            <a href="{{ route('purchases.index') }}" wire:navigate class="text-link text-sm">View all →</a>
        </div>
        @forelse ($recentPurchases as $p)
            <a href="{{ route('purchases.show', $p) }}" wire:navigate class="row-link -mx-4 flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <div>
                    <p class="font-medium">{{ $p->purchase_date->format('D, d M') }}</p>
                    <p class="text-xs text-slate-500">{{ $p->store_name ?: 'Purchase' }} · {{ $p->lines_count }} {{ Str::plural('item', $p->lines_count) }}</p>
                </div>
                <span class="font-semibold">{{ money($p->total) }}</span>
            </a>
        @empty
            <p class="py-2 text-sm text-slate-500">No purchases yet.</p>
        @endforelse
        @if ($totalPurchasesCount > 3)
            <a href="{{ route('purchases.index') }}" wire:navigate class="row-link -mx-4 -mb-4 mt-1 flex items-center justify-between rounded-b-2xl border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                <span>View all {{ $totalPurchasesCount }} purchases</span>
                <span>→</span>
            </a>
        @endif
    </div>

    <div class="card">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="card-title">Recent transfers</h2>
            <a href="{{ route('transfers.index') }}" wire:navigate class="text-link text-sm">View all →</a>
        </div>
        @forelse ($recentTransfers as $t)
            <a href="{{ route('transfers.show', $t) }}" wire:navigate class="row-link -mx-4 flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ $t->branch->name }}</p>
                    <p class="text-xs text-slate-500">Transfer #{{ $t->invoice_no }} · {{ $t->transfer_date->format('d M') }} · {{ $t->lines_count }} {{ Str::plural('item', $t->lines_count) }}</p>
                </div>
                <span class="shrink-0 font-semibold">{{ money($t->total_value) }}</span>
            </a>
        @empty
            <p class="py-2 text-sm text-slate-500">No transfers yet.</p>
        @endforelse
        @if ($totalTransfersCount > 3)
            <a href="{{ route('transfers.index') }}" wire:navigate class="row-link -mx-4 -mb-4 mt-1 flex items-center justify-between rounded-b-2xl border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                <span>View all {{ $totalTransfersCount }} transfers</span>
                <span>→</span>
            </a>
        @endif
    </div>

    <div class="card">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="card-title">Batches</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('batches.create') }}" wire:navigate class="text-link text-sm font-semibold">+ New batch</a>
                <a href="{{ route('batches.index') }}" wire:navigate class="text-link text-sm">View all →</a>
            </div>
        </div>
        @forelse ($recentBatches as $b)
            <a href="{{ route('batches.show', $b) }}" wire:navigate class="row-link -mx-4 flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <div class="min-w-0 pr-2">
                    <p class="truncate font-medium">{{ $b->batch_no }}</p>
                    <p class="text-xs text-slate-500">{{ $b->batch_date->format('d M') }} · {{ $b->lines_count }} {{ Str::plural('item', $b->lines_count) }} used</p>
                </div>
                <span class="shrink-0 font-semibold text-slate-900">{{ money($b->total_cost) }}</span>
            </a>
        @empty
            <div class="py-2 flex items-center justify-between">
                <p class="text-sm text-slate-500">No batches created yet.</p>
                <a href="{{ route('batches.create') }}" wire:navigate class="btn-primary text-xs py-1 px-2.5">+ Create batch</a>
            </div>
        @endforelse
        @if ($totalBatchesCount > 3)
            <a href="{{ route('batches.index') }}" wire:navigate class="row-link -mx-4 -mb-4 mt-1 flex items-center justify-between rounded-b-2xl border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                <span>View all {{ $totalBatchesCount }} batches</span>
                <span>→</span>
            </a>
        @endif
    </div>

    <div class="card">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="card-title">Recent activity</h2>
            <a href="{{ route('activity') }}" wire:navigate class="text-link text-sm">Activity log →</a>
        </div>
        @forelse ($recentActivity as $log)
            <a href="{{ route('activity') }}" wire:navigate class="row-link -mx-4 flex gap-3 border-t border-slate-100 px-4 py-2.5 first-of-type:border-0">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $log->isDeletion() || $log->isWarning() ? 'bg-red-500' : 'bg-teal-600' }}"></span>
                <span class="min-w-0">
                    <span class="block text-sm leading-snug">{{ $log->description }}</span>
                    <span class="block text-xs text-slate-500">{{ $log->created_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <p class="py-2 text-sm text-slate-500">No activity yet.</p>
        @endforelse
        @if ($totalActivityCount > 3)
            <a href="{{ route('activity') }}" wire:navigate class="row-link -mx-4 -mb-4 mt-1 flex items-center justify-between rounded-b-2xl border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                <span>View all {{ $totalActivityCount }} logs</span>
                <span>→</span>
            </a>
        @endif
    </div>
</div>
