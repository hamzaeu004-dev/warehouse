<?php

namespace Tests\Feature;

use App\Livewire\Items\Show;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DropDemandTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_leftover_demand_rows_no_longer_block_deleting_an_item(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::first());

        $item = Item::create(['name' => 'Old item', 'category_id' => Category::create(['name' => 'Other'])->id, 'unit' => 'kg']);
        $branch = Branch::create(['name' => 'Branch A']);

        // What a live database from the old demand feature looks like
        Schema::create('demands', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->timestamps();
        });
        Schema::create('demand_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('demand_id')->constrained();
            $t->foreignId('item_id')->constrained();
            $t->timestamps();
        });
        $demandId = DB::table('demands')->insertGetId(['branch_id' => $branch->id]);
        DB::table('demand_items')->insert(['demand_id' => $demandId, 'item_id' => $item->id]);

        (require database_path('migrations/2026_10_30_000001_drop_unused_demand_tables.php'))->up();

        $this->assertFalse(Schema::hasTable('demand_items'));
        $this->assertFalse(Schema::hasTable('demands'));

        Livewire::test(Show::class, ['item' => $item])->call('delete')->assertRedirect(route('items.index'));
        $this->assertModelMissing($item);
        $branch->delete();
        $this->assertModelMissing($branch);
    }
}
