<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Sample categories, items and branches for tests and demos. Not run by
 * `php artisan db:seed`; run it on purpose with
 * `php artisan db:seed --class=SampleDataSeeder`.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ItemSeeder::class,
            BranchSeeder::class,
        ]);
    }
}
