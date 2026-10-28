@php
    $names = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'];
    $prevName = ['day' => 'yesterday', 'week' => 'last week', 'month' => 'last month', 'year' => 'last year'][$period];
    // "+12% vs last week" with an arrow, so the direction is never colour alone
    $delta = function (float $now, float $before) use ($prevName) {
        if ($before == 0) return null;
        $pct = round(($now - $before) / abs($before) * 100);
        if ($pct == 0) return ['text' => "Same as {$prevName}", 'up' => null];
        return ['text' => ($pct > 0 ? '▲ ' : '▼ ').abs($pct)."% vs {$prevName}", 'up' => $pct > 0];
    };

    // Profit on money the branches paid (default) or on what they were invoiced
    $cash = $basis === 'cash';
    $income = $cash ? 'received' : 'sales';
    $incomeName = $cash ? 'Money received' : 'Invoiced';
    $profitKey = $cash ? 'cash_profit' : 'net_profit';
    $net = $totals[$profitKey];
    $gross = $totals[$cash ? 'cash_gross' : 'gross_profit'];
    $margin = $totals[$cash ? 'cash_margin' : 'margin'];
    $blue = '#2a78d6'; $orange = '#eb6834'; $red = '#d03b3b';
@endphp

<div class="space-y-4">
    {{-- Period type --}}
    <div class="segmented">
        @foreach ($names as $key => $name)
            <button wire:click="setPeriod('{{ $key }}')" class="{{ $period === $key ? 'is-active' : '' }}">{{ $name }}</button>
        @endforeach
    </div>

    {{-- Which day/week/month/year --}}
    <div class="flex items-center justify-between gap-2">
        <button wire:click="previous" class="btn-light h-11 w-11 shrink-0 p-0" aria-label="Previous">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="min-w-0 text-center">
            <p class="truncate font-semibold">{{ $label }}</p>
            @if ($isCurrent)
                <p class="text-xs text-teal-700">{{ ['day' => 'Today', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year'][$period] }}</p>
            @else
                <button wire:click="today" class="text-link text-xs">Go to {{ ['day' => 'today', 'week' => 'this week', 'month' => 'this month', 'year' => 'this year'][$period] }}</button>
            @endif
        </div>
        <button wire:click="next" class="btn-light h-11 w-11 shrink-0 p-0" aria-label="Next" @disabled($isCurrent)>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    {{-- Headline: profit or loss --}}
    <div class="{{ $net < 0 ? 'card bg-red-50 ring-red-200' : 'hero' }}">
        <p class="{{ $net < 0 ? 'eyebrow text-red-700' : 'hero-label' }}">{{ $net < 0 ? 'Loss' : 'Profit' }} · {{ $cash ? 'on money received' : 'on invoices' }}</p>
        <p class="{{ $net < 0 ? 'mt-1 text-3xl font-bold tracking-tight text-red-700' : 'hero-value' }}">{{ money(abs($net)) }}</p>
        <p class="{{ $net < 0 ? 'mt-1 text-sm text-red-700' : 'hero-hint' }}">
            @if ($margin !== null) Margin {{ $margin }}% @else {{ $cash ? 'No payments received in this period' : 'No invoices in this period' }} @endif
            @if ($d = $delta($net, $previousTotals[$profitKey])) · {{ $d['text'] }} @endif
        </p>
    </div>

    <div class="segmented">
        <button wire:click="setBasis('cash')" class="{{ $cash ? 'is-active' : '' }}">On money received</button>
        <button wire:click="setBasis('invoice')" class="{{ $cash ? '' : 'is-active' }}">On invoices</button>
    </div>

    {{-- The parts that make up the profit --}}
    <div class="grid grid-cols-2 gap-3">
        @foreach ([
            ['Investment', 'Stock bought (purchases)', $totals['investment'], $previousTotals['investment'], $orange],
            [$incomeName, $cash ? 'Payments from branches' : 'Invoice amounts', $totals[$income], $previousTotals[$income], $blue],
        ] as [$title, $hint, $value, $before, $color])
            <div class="card">
                <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500"><span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $color }}"></span>{{ $title }}</p>
                <p class="mt-1 text-lg font-bold">{{ money($value) }}</p>
                <p class="text-xs text-slate-500">{{ $hint }}</p>
                @if ($d = $delta($value, $before))
                    <p class="mt-1 text-xs font-medium text-slate-600">{{ $d['text'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="card divide-y divide-slate-100 py-1 text-sm">
        <div class="flex justify-between py-2.5">
            <span class="text-slate-600">{{ $incomeName }} <span class="block text-xs text-slate-400">{{ $cash ? 'what branches paid in this period' : 'what branches were billed' }}</span></span>
            <span class="font-semibold">{{ money($totals[$income]) }}</span>
        </div>
        <div class="flex justify-between py-2.5"><span class="text-slate-600">− Cost of stock sent <span class="block text-xs text-slate-400">what that stock cost the company</span></span><span class="font-semibold">{{ money(-$totals['cost']) }}</span></div>
        <div class="flex justify-between py-2.5"><span class="font-medium">= Profit before losses</span><span class="font-bold">{{ money($gross) }}</span></div>
        <div class="flex justify-between py-2.5">
            <a href="{{ route('adjustments.index') }}" wire:navigate class="text-link font-normal">{{ $totals['adjustments'] < 0 ? '− Stock loss (wastage, expired…)' : '+ Stock adjustments' }}</a>
            <span class="font-semibold">{{ money($totals['adjustments']) }}</span>
        </div>
        <div class="flex justify-between py-2.5 text-base"><span class="font-semibold">= Net {{ $net < 0 ? 'loss' : 'profit' }}</span><span class="font-bold {{ $net < 0 ? 'text-red-700' : 'text-teal-700' }}">{{ money($net) }}</span></div>
    </div>

    @if ($cash)
        <div class="notice-info">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>
                Invoiced in this period: <b>{{ money($totals['sales']) }}</b>.
                @if ($unpaidAll > 0) Branches still owe <a href="{{ route('branches') }}" wire:navigate class="font-semibold underline">{{ money($unpaidAll) }}</a> in total; it becomes profit when they pay. @endif
            </span>
        </div>
    @endif

    {{-- Trends --}}
    <div class="card">
        <h2 class="card-title">Investment vs {{ strtolower($incomeName) }}</h2>
        <p class="mb-3 text-xs text-slate-500">Last {{ count($series) }} {{ ['day' => 'days', 'week' => 'weeks', 'month' => 'months', 'year' => 'years'][$period] }} · tap a bar for details</p>
        @include('livewire.partials.bar-chart', [
            'groups' => collect($series)->map(fn ($r) => ['label' => $r['label'], 'long' => $r['long'], 'values' => [$r['investment'], $r[$income]]])->all(),
            'series' => [['name' => 'Investment', 'color' => $orange], ['name' => $incomeName, 'color' => $blue]],
            'ariaLabel' => 'Investment and '.strtolower($incomeName).' per period',
        ])
    </div>

    <div class="card">
        <h2 class="card-title">Profit / loss</h2>
        <p class="mb-3 text-xs text-slate-500">{{ $cash ? 'On money received' : 'On invoices' }} · bars below the line are a loss</p>
        @include('livewire.partials.bar-chart', [
            'groups' => collect($series)->map(fn ($r) => ['label' => $r['label'], 'long' => $r['long'], 'values' => [$r[$profitKey]]])->all(),
            'series' => [['name' => 'Profit', 'color' => $blue]],
            'negativeColor' => $red,
            'ariaLabel' => 'Profit per period',
        ])
    </div>

    <details class="card group p-0">
        <summary class="text-link flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm [&::-webkit-details-marker]:hidden">
            <span>Show as table</span>
            <svg class="h-4 w-4 transition group-open:rotate-90" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </summary>
        <div class="overflow-x-auto border-t border-slate-100">
            <table class="w-full text-right text-xs">
                <thead class="text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Period</th>
                        <th class="px-2 font-medium">Invested</th>
                        <th class="px-2 font-medium">Received</th>
                        <th class="px-2 font-medium">Stock cost</th>
                        <th class="px-3 font-medium">Profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 tabular-nums">
                    @foreach (array_reverse($series) as $r)
                        <tr>
                            <td class="px-3 py-2 text-left">{{ $r['long'] }}</td>
                            <td class="px-2">{{ money($r['investment']) }}</td>
                            <td class="px-2">{{ money($r[$income]) }}</td>
                            <td class="px-2">{{ money($r['cost']) }}</td>
                            <td class="px-3 font-semibold {{ $r[$profitKey] < 0 ? 'text-red-700' : '' }}">{{ money($r[$profitKey]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    {{-- Who made the profit --}}
    <div class="card p-0">
        <h2 class="card-title px-4 pb-1 pt-4">Profit by branch</h2>
        @forelse ($branches as $b)
            @php $profit = ($cash ? $b->received : $b->sales) - $b->cost; @endphp
            <a href="{{ route('branches.show', $b->id) }}" wire:navigate class="row-link flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <div class="min-w-0">
                    <p class="font-medium">{{ $b->name }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $cash ? 'Paid' : 'Invoiced' }} {{ money($cash ? $b->received : $b->sales) }} · stock cost {{ money($b->cost) }}
                    </p>
                </div>
                <span class="shrink-0 font-semibold {{ $profit < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ money($profit) }}</span>
            </a>
        @empty
            <p class="px-4 pb-4 text-sm text-slate-500">No invoices or payments in this period.</p>
        @endforelse
    </div>

    <div class="card p-0">
        <h2 class="card-title px-4 pt-4">Most profitable items</h2>
        <p class="px-4 pb-1 text-xs text-slate-500">On invoice rates, since a payment covers a whole invoice, not single items</p>
        @forelse ($items as $it)
            @php $profit = $it->sales - $it->cost; @endphp
            <a href="{{ route('items.show', $it->id) }}" wire:navigate class="row-link flex items-center justify-between border-t border-slate-100 px-4 py-3 first-of-type:border-0">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ $it->name }}</p>
                    <p class="text-xs text-slate-500">{{ qty($it->qty) }} {{ $it->unit }} sent · invoiced {{ money($it->sales) }}</p>
                </div>
                <span class="shrink-0 font-semibold {{ $profit < 0 ? 'text-red-700' : '' }}">{{ money($profit) }}</span>
            </a>
        @empty
            <p class="px-4 pb-4 text-sm text-slate-500">No items sent in this period.</p>
        @endforelse
    </div>

    <details class="px-1 text-xs text-slate-500">
        <summary class="text-link cursor-pointer">How is this calculated?</summary>
        <div class="mt-2 space-y-1.5">
            <p><b>Profit on money received</b> = what branches paid in this period − what the stock sent in this period cost the company − stock lost (wastage, expired). Example: stock that cost Rs 800 goes to a branch and it pays Rs 1,000 → Rs 200 profit.</p>
            <p><b>Profit on invoices</b> = what branches were billed − stock cost − stock lost. Use it to see profit before payments come in.</p>
            <p><b>Timing:</b> if a branch pays next month for this month's stock, this month shows the cost and next month shows the payment. Over a full year it evens out.</p>
            <p><b>Investment</b> = purchases in this period. It is not taken off profit: stock still in the warehouse is not lost money. Weeks start on {{ $weekStart }}.</p>
        </div>
    </details>
</div>
