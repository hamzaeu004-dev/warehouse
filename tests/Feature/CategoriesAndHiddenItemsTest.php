<?php

namespace Tests\Feature;

use App\Livewire\Categories;
use App\Livewire\Items\Index as ItemsIndex;
use App\Livewire\Items\Show as ItemShow;
use App\Livewire\Purchases\Create as PurchaseCreate;
use App\Livewire\Purchases\Pickable;
use App\Livewire\Transfers\Create as TransferCreate;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriesAndHiddenItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::first());
    }

    // --- Categories ---------------------------------------------------------

    public function test_add_rename_and_reorder(): void
    {
        $this->get('/categories')->assertOk()->assertSee('Spices');

        Livewire::test(Categories::class)->set('newName', 'Vegetables')->call('add')->assertHasNoErrors();
        $veg = Category::where('name', 'Vegetables')->firstOrFail();
        $this->assertSame((int) Category::max('sort_order'), $veg->sort_order);

        Livewire::test(Categories::class)->set('newName', 'Spices')->call('add')->assertHasErrors('newName');

        Livewire::test(Categories::class)->call('edit', $veg->id)->set('editName', 'Fresh vegetables')->call('update');
        $this->assertSame('Fresh vegetables', $veg->fresh()->name);
        $this->assertSame('Category renamed: Vegetables → Fresh vegetables', ActivityLog::latest('id')->first()->description);

        // Move up one place
        $ordered = fn () => Category::orderBy('sort_order')->pluck('name')->all();
        $before = $ordered();
        Livewire::test(Categories::class)->call('move', $veg->id, -1);
        $after = $ordered();
        $this->assertSame('Fresh vegetables', $after[count($after) - 2]);
        $this->assertSame($before[count($before) - 2], end($after));
    }

    public function test_empty_category_is_deleted_straight_away(): void
    {
        $empty = Category::create(['name' => 'Temp', 'sort_order' => 99]);

        Livewire::test(Categories::class)->call('delete', $empty->id);

        $this->assertModelMissing($empty);
    }

    public function test_category_with_items_moves_them_before_delete(): void
    {
        $packing = Category::where('name', 'Packing')->firstOrFail();
        $other = Category::where('name', 'Other')->firstOrFail();
        $count = $packing->items()->count();

        Livewire::test(Categories::class)
            ->call('delete', $packing->id)
            ->assertSet('deletingId', $packing->id)
            ->set('moveTo', $other->id)
            ->call('moveItemsAndDelete')
            ->assertHasNoErrors();

        $this->assertModelMissing($packing);
        $this->assertSame($count, $other->items()->count());
        $this->assertStringContainsString("{$count} items moved to Other", ActivityLog::latest('id')->first()->description);
    }

    public function test_move_is_blocked_when_names_clash(): void
    {
        $sauce = Category::where('name', 'Sauce')->firstOrFail();
        $other = Category::where('name', 'Other')->firstOrFail();
        Item::create(['category_id' => $other->id, 'name' => 'Honey', 'unit' => 'bottle']);

        Livewire::test(Categories::class)
            ->call('delete', $sauce->id)
            ->set('moveTo', $other->id)
            ->call('moveItemsAndDelete')
            ->assertHasErrors('moveTo');

        $this->assertModelExists($sauce);
    }

    public function test_quick_add_from_purchase_works_even_if_other_was_deleted(): void
    {
        Category::where('name', 'Other')->delete();

        Livewire::test(PurchaseCreate::class)->call('createItem', 'Soya sauce', 'bottle', null);

        $this->assertSame('Other', Item::where('name', 'Soya sauce')->first()->category->name);
    }

    // --- Hidden items -------------------------------------------------------

    public function test_hiding_keeps_history_and_removes_item_from_forms(): void
    {
        $honey = Item::where('name', 'Honey')->firstOrFail();
        app(StockService::class)->recordPurchase(['purchase_date' => now()->toDateString()], [
            ['item_id' => $honey->id, 'qty' => 3, 'unit_price' => 900],
        ]);

        Livewire::test(ItemShow::class, ['item' => $honey])->call('toggleHidden');

        $this->assertFalse($honey->fresh()->is_active);
        $this->assertSame('Item hidden (no longer used): Honey (3 bottle still in stock)', ActivityLog::latest('id')->first()->description);
        $this->assertNotContains($honey->id, array_column(Pickable::all(), 'id'));

        // Not in the normal stock list, but in "Hidden"
        Livewire::test(ItemsIndex::class)->assertDontSee('Honey');
        Livewire::test(ItemsIndex::class)->set('filter', 'hidden')->assertSee('Honey');

        // History page still opens, and old data is untouched
        $this->get("/items/{$honey->id}")->assertOk()->assertSee('Hidden item')->assertSee('Purchase');
        $this->assertEquals(3, (float) $honey->fresh()->stock_qty);
    }

    public function test_hidden_item_cannot_be_saved_from_an_old_screen(): void
    {
        $honey = Item::where('name', 'Honey')->firstOrFail();
        app(StockService::class)->recordPurchase(['purchase_date' => now()->toDateString()], [
            ['item_id' => $honey->id, 'qty' => 3, 'unit_price' => 900],
        ]);
        $honey->update(['is_active' => false]);

        Livewire::test(PurchaseCreate::class)
            ->call('save', [['item_id' => $honey->id, 'qty' => '1', 'unit_price' => '900']])
            ->assertHasErrors('lines.0.item_id');

        Livewire::test(TransferCreate::class)
            ->set('branch_id', Branch::first()->id)
            ->call('save', [['item_id' => $honey->id, 'qty' => '1', 'rate' => '1000']])
            ->assertHasErrors('lines.0.item_id');
    }

    public function test_use_again_brings_it_back(): void
    {
        $honey = Item::where('name', 'Honey')->firstOrFail();
        $honey->update(['is_active' => false]);

        Livewire::test(ItemShow::class, ['item' => $honey])->call('toggleHidden');

        $this->assertTrue($honey->fresh()->is_active);
        $this->assertContains($honey->id, array_column(Pickable::all(), 'id'));
        $this->assertSame('Item back in use: Honey', ActivityLog::latest('id')->first()->description);
    }

    public function test_delete_button_only_without_history(): void
    {
        $salt = Item::where('name', 'Salt')->firstOrFail();
        $this->get("/items/{$salt->id}")->assertSee('Delete this item permanently?');

        app(StockService::class)->recordPurchase(['purchase_date' => now()->toDateString()], [
            ['item_id' => $salt->id, 'qty' => 1, 'unit_price' => 100],
        ]);
        $this->get("/items/{$salt->id}")->assertDontSee('Delete this item permanently?')->assertSee('hide it instead');
    }

    public function test_item_category_can_be_edited(): void
    {
        $item = Item::where('name', 'Salt')->firstOrFail();
        $newCategory = Category::create(['name' => 'Seasoning', 'sort_order' => 50]);

        Livewire::test(ItemShow::class, ['item' => $item])
            ->call('edit')
            ->set('category_id', $newCategory->id)
            ->set('name', 'Table Salt')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($newCategory->id, $item->fresh()->category_id);
        $this->assertSame('Table Salt', $item->fresh()->name);
        $this->assertSame('Seasoning', $item->fresh()->category->name);
    }

    public function test_newly_added_category_can_be_edited_and_deleted(): void
    {
        $component = Livewire::test(Categories::class)
            ->set('newName', 'Bakery')
            ->call('add')
            ->assertHasNoErrors();

        $bakery = Category::where('name', 'Bakery')->firstOrFail();

        // Rename it
        $component->call('edit', $bakery->id)
            ->assertSet('editingId', $bakery->id)
            ->set('editName', 'Fresh Bakery')
            ->call('update')
            ->assertHasNoErrors()
            ->assertSet('editingId', null);

        $this->assertSame('Fresh Bakery', $bakery->fresh()->name);

        // Prompt delete and delete it
        $component->call('promptDelete', $bakery->id)
            ->assertSet('deletingId', $bakery->id)
            ->call('delete', $bakery->id)
            ->assertHasNoErrors();

        $this->assertModelMissing($bakery);
    }
}
