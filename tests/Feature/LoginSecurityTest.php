<?php

namespace Tests\Feature;

use App\Livewire\Account;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function withoutAdminPasswordEnv(callable $callback): void
    {
        $old = $_ENV['ADMIN_PASSWORD'] ?? null;
        unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);
        putenv('ADMIN_PASSWORD');

        try {
            $callback();
        } finally {
            $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = $old;
            putenv("ADMIN_PASSWORD={$old}");
        }
    }

    public function test_seeder_without_env_password_makes_a_random_one(): void
    {
        $this->withoutAdminPasswordEnv(function () {
            $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        });

        $user = User::first();
        $this->assertNotNull($user);
        foreach (['manager123', 'password', 'Test-pass-123', ''] as $guess) {
            $this->assertFalse(Hash::check($guess, $user->password), "Guessable password: {$guess}");
        }
    }

    public function test_seeding_again_never_resets_a_changed_password(): void
    {
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        $user = User::first();
        $user->update(['password' => Hash::make('My-own-pass-9'), 'password_changed_at' => now()]);

        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('My-own-pass-9', $user->fresh()->password));
    }

    public function test_seeding_again_does_not_bring_back_renamed_branches(): void
    {
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        Branch::first()->update(['name' => 'ROOSTERS - RAHEEM GARDEN']);

        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);

        $this->assertSame(3, Branch::count());
        $this->assertFalse(Branch::where('name', 'Branch 1')->exists());
    }

    public function test_warning_shows_until_password_is_changed(): void
    {
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        $user = User::first();
        $this->actingAs($user);

        $this->get('/')->assertSee('you are still using the setup password');

        Livewire::test(Account::class)
            ->set('current_password', 'Test-pass-123')
            ->set('password', 'Warehouse2026')
            ->set('password_confirmation', 'Warehouse2026')
            ->call('savePassword')
            ->assertHasNoErrors();

        $this->assertNotNull($user->fresh()->password_changed_at);
        $this->actingAs($user->fresh())->get('/')->assertDontSee('you are still using the setup password');
    }

    public function test_weak_or_same_passwords_are_rejected(): void
    {
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        $this->actingAs(User::first());

        foreach (['short1', 'onlyletters', '12345678', 'Test-pass-123'] as $weak) {
            Livewire::test(Account::class)
                ->set('current_password', 'Test-pass-123')
                ->set('password', $weak)
                ->set('password_confirmation', $weak)
                ->call('savePassword')
                ->assertHasErrors('password');
        }

        $this->assertTrue(Hash::check('Test-pass-123', User::first()->password));
    }
}
