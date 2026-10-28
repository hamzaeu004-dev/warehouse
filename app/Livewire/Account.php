<?php

namespace App\Livewire;

use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Account')]
class Account extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,'.$user->id],
        ]);
        $before = $user->only(['name', 'email']);
        $user->update($data);

        $changes = [];
        foreach (['name', 'email'] as $field) {
            if ($before[$field] !== $user->{$field}) {
                $changes[] = "{$field} {$before[$field]} → {$user->{$field}}";
            }
        }
        if ($changes) {
            ActivityLogger::record('account.profile', 'Profile updated: '.implode(', ', $changes));
        }

        session()->flash('success', 'Profile saved.');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Current password is incorrect.',
            'password.different' => 'New password must be different from the current one.',
        ]);

        auth()->user()->update(['password' => Hash::make($this->password), 'password_changed_at' => now()]);
        ActivityLogger::record('account.password', 'Password changed');
        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('success', 'Password changed.');
    }

    public function cleanTestData(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \App\Models\BatchItem::truncate();
        \App\Models\Batch::truncate();
        \App\Models\TransferItem::truncate();
        \App\Models\Transfer::truncate();
        \App\Models\PurchaseItem::truncate();
        \App\Models\Purchase::truncate();
        \App\Models\AdjustmentItem::truncate();
        \App\Models\Adjustment::truncate();
        \App\Models\BranchPayment::truncate();
        \App\Models\ActivityLog::truncate();
        \App\Models\Item::query()->update([
            'stock_qty' => 0,
            'avg_cost' => 0,
            'last_price' => null,
        ]);
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $this->dispatch('clear-browser-drafts');
        session()->flash('success', 'Tamam testing transactions saaf kar di gayi hain aur item stock 0 ho gaya hai.');
    }

    public function render()
    {
        return view('livewire.account');
    }
}
