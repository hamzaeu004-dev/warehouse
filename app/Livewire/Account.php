<?php

namespace App\Livewire;

use App\Services\ActivityLogger;
use App\Services\DataReset;
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

    public string $reset_password = '';

    /**
     * Clears test data. transactions keeps items, categories and branches;
     * everything removes those too. Needs the current password.
     */
    public function resetData(string $scope): void
    {
        $this->validate(
            ['reset_password' => ['required', 'current_password']],
            ['reset_password.required' => 'Enter your password to confirm.', 'reset_password.current_password' => 'Password is incorrect.']
        );

        if ($scope === 'everything') {
            DataReset::everything();
            session()->flash('success', 'All data cleared. Add your branches and items to start.');
        } else {
            DataReset::transactions();
            session()->flash('success', 'Test transactions cleared. Stock is 0; items and branches are kept.');
        }

        $this->reset('reset_password');
        $this->dispatch('clear-browser-drafts');
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.account');
    }
}
