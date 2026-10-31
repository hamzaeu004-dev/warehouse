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

    {{-- Clearing test data: needs the password, two levels --}}
    <div class="card space-y-3 ring-red-200" x-data @clear-browser-drafts.window="try { localStorage.clear() } catch (e) {}">
        <div>
            <h2 class="card-title">Start fresh</h2>
            <p class="mt-1 text-sm text-slate-500">Remove test data before using the app for real. Your login stays. This cannot be undone.</p>
        </div>
        <div>
            <label class="label" for="reset_password">Your password</label>
            <input id="reset_password" wire:model="reset_password" type="password" autocomplete="current-password" class="input" placeholder="Needed to confirm">
            @error('reset_password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button wire:click="resetData('transactions')"
                wire:confirm="Delete all batches, purchases, invoices, adjustments and payments, and set every item's stock to 0? Items, categories and branches are kept."
                class="btn-light w-full text-red-600 hover:bg-red-50">
            Clear transactions only
        </button>
        <button wire:click="resetData('everything')"
                wire:confirm="Delete EVERYTHING except your login: all items, categories, branches, purchases, invoices and payments? You will add them again by hand."
                class="btn-danger w-full">
            Delete everything and start empty
        </button>
        <p class="text-xs text-slate-500"><b>Transactions only:</b> keeps your item list and branches. <b>Everything:</b> also removes items, categories and branches.</p>
    </div>
</div>
