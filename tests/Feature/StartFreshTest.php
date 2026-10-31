<?php

namespace Tests\Feature;

use App\Livewire\Account;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Transfer;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StartFreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_seed_creates_only_the_login(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame(0, Item::count());
        $this->assertSame(0, Category::count());
        $this->assertSame(0, Branch::count());
    }

    public function test_old_unprotected_clean_link_is_gone(): void
    {
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        $itemsBefore = Item::count();

        // Was a public GET that wiped everything; now it does not exist
        $this->get('/clean-test-data')->assertNotFound();
        $this->actingAs(User::first())->get('/clean-test-data')->assertNotFound();
        $this->assertSame($itemsBefore, Item::count());
    }

    private function withSomeData(): Item
    {
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        $this->actingAs(User::first());
        $salt = Item::where('name', 'Salt')->first();
        $stock = app(StockService::class);
        $stock->recordPurchase(['purchase_date' => now()->toDateString()], [['item_id' => $salt->id, 'qty' => 10, 'unit_price' => 60]]);
        $stock->recordTransfer(['branch_id' => Branch::first()->id, 'transfer_date' => now()->toDateString()], [['item_id' => $salt->id, 'qty' => 2, 'rate' => 80]]);

        return $salt;
    }

    public function test_reset_needs_the_password(): void
    {
        $this->withSomeData();

        Livewire::test(Account::class)->call('resetData', 'everything')->assertHasErrors('reset_password');
        Livewire::test(Account::class)->set('reset_password', 'wrong')->call('resetData', 'everything')->assertHasErrors('reset_password');

        $this->assertSame(1, Purchase::count());
        $this->assertGreaterThan(0, Item::count());
    }

    public function test_clear_transactions_keeps_items_and_branches(): void
    {
        $salt = $this->withSomeData();

        Livewire::test(Account::class)->set('reset_password', 'Test-pass-123')->call('resetData', 'transactions')->assertHasNoErrors();

        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, Transfer::count());
        $this->assertEquals(0, (float) $salt->fresh()->stock_qty);
        $this->assertNull($salt->fresh()->last_price);
        $this->assertGreaterThan(0, Item::count());
        $this->assertSame(3, Branch::count());
        $this->assertSame(1, ActivityLog::count());   // only the "cleared" note
    }

    public function test_delete_everything_leaves_only_the_login(): void
    {
        $this->withSomeData();

        Livewire::test(Account::class)->set('reset_password', 'Test-pass-123')->call('resetData', 'everything')->assertHasNoErrors();

        $this->assertSame(0, Item::count());
        $this->assertSame(0, Category::count());
        $this->assertSame(0, Branch::count());
        $this->assertSame(0, Purchase::count());
        $this->assertSame(1, User::count());

        // The app still opens on an empty database
        foreach (['/', '/items', '/purchases/new', '/transfers/new', '/branches', '/categories', '/order-list', '/analysis'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_reset_also_clears_batches(): void
    {
        $salt = $this->withSomeData();
        app(StockService::class)->recordBatch(['batch_date' => now()->toDateString()], [['item_id' => $salt->id, 'qty' => 3]]);
        $this->assertSame(1, \App\Models\Batch::count());

        Livewire::test(Account::class)->set('reset_password', 'Test-pass-123')->call('resetData', 'everything')->assertHasNoErrors();

        $this->assertSame(0, \App\Models\Batch::count());
        $this->assertSame(0, \App\Models\BatchItem::count());
        $this->assertSame(0, Item::count());
    }

    public function test_artisan_command_asks_before_deleting(): void
    {
        $this->withSomeData();

        $this->artisan('app:clean-test-data')->expectsConfirmation('This deletes all batches, purchases, invoices, adjustments and payments, and set stock to 0. Continue?', 'no');
        $this->assertSame(1, Purchase::count());

        $this->artisan('app:clean-test-data --all --force')->assertSuccessful();
        $this->assertSame(0, Item::count());
        $this->assertSame(1, User::count());
    }
}
