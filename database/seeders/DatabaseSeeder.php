<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Only the login. Items, categories and branches are entered in the app;
        // SampleDataSeeder has a sample list for tests and demos.
        $this->createManager();
    }

    /**
     * Creates the manager login only when there is no user yet, so running
     * the seeder again never resets a password that was changed in the app.
     * No password lives in the code: it comes from ADMIN_PASSWORD in .env,
     * or a random one is generated and shown once in the terminal.
     */
    private function createManager(): void
    {
        if (User::exists()) {
            $this->command?->info('Manager login already exists, left unchanged.');

            return;
        }

        $email = env('ADMIN_EMAIL') ?: 'manager@inventory.test';
        $password = env('ADMIN_PASSWORD') ?: Str::password(14, symbols: false);

        User::create([
            'name' => env('ADMIN_NAME') ?: 'Manager',
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn('Manager login created. Write this down, it will not be shown again:');
            $this->command?->line("  Email:    {$email}");
            $this->command?->line("  Password: {$password}");
            $this->command?->warn('Change it after the first login (More > Account).');
        }
    }
}
