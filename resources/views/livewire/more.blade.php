@php
    $groups = [
        'Reports' => [
            ['url' => route('reports'), 'label' => 'Owner report (PDF)', 'hint' => 'Purchases, deliveries and wastage to download or share', 'color' => 'bg-rose-100 text-rose-700', 'icon' => 'M12 10v6m0 0l-3-3m3 3l3-3M7 21h10a2 2 0 002-2V9.4a1 1 0 00-.3-.7l-5.4-5.4a1 1 0 00-.7-.3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
            ['url' => route('analysis'), 'label' => 'Analysis', 'hint' => 'Profit and investment: daily, weekly, monthly, yearly', 'color' => 'bg-emerald-100 text-emerald-700', 'icon' => 'M3 3v18h18M7 15l4-4 3 3 5-6'],
            ['url' => route('order-list'), 'label' => 'Order list', 'hint' => 'What to buy this week', 'color' => 'bg-violet-100 text-violet-700', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
            ['url' => route('activity'), 'label' => 'Activity log', 'hint' => 'Everything that happened in the system', 'color' => 'bg-slate-200 text-slate-700', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ],
        'History' => [
            ['url' => route('purchases.index'), 'label' => 'Purchases', 'hint' => 'All stock bought', 'color' => 'bg-teal-100 text-teal-700', 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z'],
            ['url' => route('batches.index'), 'label' => 'Batches', 'hint' => 'Items used from stock for preparation', 'color' => 'bg-orange-100 text-orange-700', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
            ['url' => route('transfers.index'), 'label' => 'Transfers', 'hint' => 'Stock sent to branches', 'color' => 'bg-sky-100 text-sky-700', 'icon' => 'M9 17h6M9 13h6M9 9h1m-4 12h12a2 2 0 002-2V7.4a2 2 0 00-.6-1.4l-3.4-3.4a2 2 0 00-1.4-.6H6a2 2 0 00-2 2v15a2 2 0 002 2z'],
            ['url' => route('adjustments.index'), 'label' => 'Stock adjustments', 'hint' => 'Wastage, expired, damaged, counts', 'color' => 'bg-amber-100 text-amber-700', 'icon' => 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z'],
        ],
        'Setup' => [
            ['url' => route('branches'), 'label' => 'Branches', 'hint' => 'Names, ATTN, contact & address', 'color' => 'bg-indigo-100 text-indigo-700', 'icon' => 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6'],
            ['url' => route('categories'), 'label' => 'Categories', 'hint' => 'Add, rename, reorder item groups', 'color' => 'bg-cyan-100 text-cyan-700', 'icon' => 'M4 6h7v7H4zM13 6h7v7h-7zM4 15h7v5H4zM13 15h7v5h-7z'],
            ['url' => route('account'), 'label' => 'Account', 'hint' => 'Name, email, password', 'color' => 'bg-slate-200 text-slate-700', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ],
    ];
@endphp

<div class="space-y-5">
    @foreach ($groups as $title => $links)
        <div>
            <p class="eyebrow mb-2 px-1">{{ $title }}</p>
            <div class="card divide-y divide-slate-100 p-0">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" wire:navigate class="row-link flex items-center gap-3 px-4 py-3.5 first:rounded-t-2xl last:rounded-b-2xl">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $link['color'] }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/></svg>
                        </span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-slate-900">{{ $link['label'] }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $link['hint'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn-light w-full text-red-600">Logout</button>
    </form>
</div>
