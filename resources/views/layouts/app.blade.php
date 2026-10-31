<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#115e59">
    <title>{{ $title ?? 'Home' }} · {{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen antialiased">
    @php
        // Where Back goes when there is no earlier screen in this visit (page opened directly or refreshed)
        $parents = [
            'items.index' => 'dashboard',
            'items.show' => 'items.index',
            'purchases.create' => 'dashboard',
            'purchases.index' => 'more',
            'purchases.show' => 'purchases.index',
            'purchases.edit' => 'purchases.index',
            'transfers.create' => 'dashboard',
            'transfers.index' => 'more',
            'transfers.show' => 'transfers.index',
            'transfers.edit' => 'transfers.index',
            'adjustments.create' => 'adjustments.index',
            'adjustments.index' => 'more',
            'adjustments.show' => 'adjustments.index',
            'batches.create' => 'batches.index',
            'batches.index' => 'more',
            'batches.show' => 'batches.index',
            'activity' => 'more',
            'analysis' => 'dashboard',
            'reports' => 'more',
            'order-list' => 'dashboard',
            'branches' => 'more',
            'categories' => 'items.index',
            'branches.show' => 'branches',
            'account' => 'more',
            'more' => 'dashboard',
        ];
        $parent = $parents[request()->route()?->getName()] ?? null;
        $user = auth()->user();
    @endphp

    <header class="sticky top-0 z-30 bg-gradient-to-r from-teal-800 to-teal-700 text-white shadow-md shadow-teal-950/10">
        <div class="mx-auto flex h-14 max-w-3xl items-center gap-1 px-2">
            @if ($parent)
                <button type="button" onclick="goBack(@js(route($parent)))" aria-label="Back"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition hover:bg-white/10 active:bg-white/20">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
            @else
                <span class="ml-2 mr-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/15">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </span>
            @endif
            <h1 class="min-w-0 flex-1 truncate text-[17px] font-semibold tracking-tight">{{ $title ?? 'Warehouse' }}</h1>
            @if ($user)
                <a href="{{ route('account') }}" wire:navigate aria-label="Account"
                   class="mr-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15 text-sm font-bold uppercase ring-1 ring-white/25 transition hover:bg-white/25">
                    {{ mb_substr($user->name, 0, 1) }}
                </a>
            @endif
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 pb-32 pt-4">
        @if ($user?->usesSetupPassword() && ! request()->routeIs('account'))
            <a href="{{ route('account') }}" wire:navigate class="notice-warn row-link mb-4 hover:bg-amber-100">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
                <span><b>Security:</b> you are still using the setup password. Tap here to set your own email and password.</span>
            </a>
        @endif

        @if (session('success'))
            <div class="notice-success mb-4 font-medium" x-data="{ open: true }" x-show="open" x-init="setTimeout(() => open = false, 5000)" x-transition.opacity>
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{ $slot }}
    </main>

    @php
        $tabs = [
            ['route' => 'dashboard', 'match' => ['dashboard', 'analysis', 'order-list'], 'label' => 'Home', 'icon' => 'M3 12l9-9 9 9M5 10v10h4v-6h6v6h4V10'],
            ['route' => 'items.index', 'match' => ['items.*', 'categories'], 'label' => 'Stock', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['route' => 'purchases.create', 'match' => 'purchases.*', 'label' => 'Purchase', 'icon' => 'M12 4v16m8-8H4'],
            ['route' => 'transfers.create', 'match' => 'transfers.*', 'label' => 'Send', 'icon' => 'M9 17h6M9 13h6M9 9h1m-4 12h12a2 2 0 002-2V7.4a2 2 0 00-.6-1.4l-3.4-3.4a2 2 0 00-1.4-.6H6a2 2 0 00-2 2v15a2 2 0 002 2z'],
            ['route' => 'more', 'match' => ['more', 'branches', 'branches.show', 'account', 'adjustments.*', 'batches.*', 'activity', 'reports'], 'label' => 'More', 'icon' => 'M4 6h16M4 12h16M4 18h16'],
        ];
    @endphp
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/80 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur">
        <div class="mx-auto grid max-w-3xl grid-cols-5 px-1">
            @foreach ($tabs as $tab)
                @php $active = request()->routeIs($tab['match']); @endphp
                <a href="{{ route($tab['route']) }}" wire:navigate
                   class="group flex flex-col items-center gap-0.5 pb-2 pt-1.5 text-[11px] font-semibold transition-colors {{ $active ? 'text-teal-700' : 'text-slate-500 hover:text-teal-700' }}">
                    @if ($tab['label'] === 'Purchase')
                        <span class="-mt-6 flex h-[52px] w-[52px] items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-teal-800 text-white shadow-lg shadow-teal-900/30 ring-4 ring-white transition group-active:scale-95">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/></svg>
                        </span>
                    @else
                        <span class="flex h-8 w-14 items-center justify-center rounded-full transition {{ $active ? 'bg-teal-50' : 'group-hover:bg-slate-100' }}">
                            <svg class="h-[22px] w-[22px]" fill="none" stroke="currentColor" stroke-width="{{ $active ? '2.3' : '2' }}" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/></svg>
                        </span>
                    @endif
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
    @livewireScriptConfig
</body>
</html>
