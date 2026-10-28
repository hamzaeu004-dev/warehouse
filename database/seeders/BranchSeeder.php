<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        // Only on an empty database: once branches are renamed, seeding again must not add "Branch 1" back
        if (Branch::exists()) {
            return;
        }

        // Placeholder names, rename from the Branches page
        foreach (['Branch 1', 'Branch 2', 'Branch 3'] as $name) {
            Branch::create(['name' => $name]);
        }
    }
}
