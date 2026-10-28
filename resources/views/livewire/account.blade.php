<div class="space-y-4">
    @if (auth()->user()->usesSetupPassword())
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <b>You are still using the setup login.</b> Change the email to your own and set a new password below. Keep the password to yourself.
        </div>
    @endif

    <form wire:submit="saveProfile" class="card space-y-3">
        <h2 class="font-semibold">Profile</h2>
        <div>
            <label class="label">Name</label>
            <input wire:model="name" class="input">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Email (used to log in)</label>
            <input wire:model="email" type="email" class="input">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button class="btn-primary w-full">Save</button>
    </form>

    <form wire:submit="savePassword" class="card space-y-3">
        <h2 class="font-semibold">Change password</h2>
        <div>
            <label class="label">Current password</label>
            <input wire:model="current_password" type="password" autocomplete="current-password" class="input">
            @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">New password <span class="font-normal text-slate-400">(8+ characters, letters and numbers)</span></label>
            <input wire:model="password" type="password" autocomplete="new-password" class="input">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Confirm new password</label>
            <input wire:model="password_confirmation" type="password" autocomplete="new-password" class="input">
        </div>
        <button class="btn-primary w-full">Save password</button>
    </form>

    <div class="card space-y-3" x-data @clear-browser-drafts.window="localStorage.clear()">
        <h2 class="font-semibold text-slate-900">Database & Test Data</h2>
        <p class="text-xs text-slate-500">
            Agar aap ne testing ke doraan purchases, invoices, batches ya adjustments enter kiye the, unhein saaf kar ke stock 0 kar sakte hain. Items aur branches mehfooz rahein gi.
        </p>
        <button wire:click="cleanTestData" wire:confirm="Kya aap waqai tamam testing transactions (Invoices, Purchases, Batches, Adjustments) delete kar ke stock 0 karna chahte hain?" class="btn-light w-full text-red-600 hover:bg-red-50">
            Clean All Testing Data
        </button>
    </div>
</div>
