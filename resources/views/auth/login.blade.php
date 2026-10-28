<x-layouts.guest title="Login">
    <div class="relative flex min-h-screen flex-col">
        {{-- Brand band --}}
        <div class="bg-gradient-to-br from-teal-800 via-teal-700 to-emerald-600 px-6 pb-24 pt-14 text-center text-white">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 shadow-lg ring-1 ring-white/25">
                <svg class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <h1 class="mt-4 text-2xl font-bold tracking-tight">Warehouse Inventory</h1>
            <p class="mt-1 text-sm text-teal-50/90">Purchases, branch invoices and stock in one place</p>
        </div>

        <div class="-mt-16 flex-1 px-4 pb-10">
            <form method="POST" action="{{ route('login') }}" class="card mx-auto w-full max-w-sm space-y-4 p-6">
                @csrf
                <div>
                    <p class="text-lg font-semibold">Manager login</p>
                    <p class="text-sm text-slate-500">Sign in to continue</p>
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div x-data="{ show: false }">
                    <label class="label" for="password">Password</label>
                    <div class="relative">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" required autocomplete="current-password" class="input pr-12">
                        <button type="button" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'"
                                class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-500 hover:text-teal-700 active:text-teal-800">
                            {{-- Eye icon: shown while the password is hidden --}}
                            <svg x-show="!show" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            {{-- Eye-off icon: shown while the password is visible --}}
                            <svg x-show="show" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 4.6A9.9 9.9 0 0112 4.5c6 0 9.75 7.5 9.75 7.5a17 17 0 01-3.2 4.1M6.6 6.6C3.9 8.4 2.25 12 2.25 12s3.75 7.5 9.75 7.5a9.6 9.6 0 004.4-1.1"/></svg>
                        </button>
                    </div>
                </div>
                <button class="btn-primary w-full">Login</button>
            </form>
        </div>
    </div>
</x-layouts.guest>
