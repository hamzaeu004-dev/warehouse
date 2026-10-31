<?php

namespace Tests\Feature;

use App\Livewire\Account;
use App\Livewire\Branches;
use App\Livewire\Items\Index as ItemsIndex;
use App\Livewire\Items\Show as ItemShow;
use App\Livewire\Purchases\Create as PurchaseCreate;
use App\Livewire\Transfers\Create as TransferCreate;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
    }

    private function login(): User
    {
        $user = User::first();
        $this->actingAs($user);

        return $user;
    }

    private function buy(Item $item, float $qty, float $price): void
    {
        app(StockService::class)->recordPurchase(['purchase_date' => now()->toDateString()], [
            ['item_id' => $item->id, 'qty' => $qty, 'unit_price' => $price],
        ]);
    }

    // --- Access -----------------------------------------------------------

    public function test_every_page_redirects_guests_to_login(): void
    {
        foreach (['/', '/items', '/items/1', '/purchases', '/purchases/new', '/purchases/1', '/transfers', '/transfers/new', '/transfers/1', '/branches', '/account', '/more'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->post('/login', ['email' => 'manager@inventory.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'manager@inventory.test', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'manager@inventory.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_missing_records_return_404(): void
    {
        $this->login();
        $this->get('/items/99999')->assertNotFound();
        $this->get('/purchases/99999')->assertNotFound();
        $this->get('/transfers/99999')->assertNotFound();
    }

    // --- Bad input on purchase --------------------------------------------

    public function test_purchase_rejects_bad_lines(): void
    {
        $this->login();
        $id = Item::first()->id;

        $cases = [
            'negative qty' => [['item_id' => $id, 'qty' => '-5', 'unit_price' => '10']],
            'zero qty' => [['item_id' => $id, 'qty' => '0', 'unit_price' => '10']],
            'text qty' => [['item_id' => $id, 'qty' => 'abc', 'unit_price' => '10']],
            'negative price' => [['item_id' => $id, 'qty' => '1', 'unit_price' => '-1']],
            'missing price' => [['item_id' => $id, 'qty' => '1', 'unit_price' => '']],
            'unknown item' => [['item_id' => 99999, 'qty' => '1', 'unit_price' => '10']],
            'huge qty' => [['item_id' => $id, 'qty' => '999999999999', 'unit_price' => '10']],
            'huge price' => [['item_id' => $id, 'qty' => '1', 'unit_price' => '99999999999999']],
        ];

        foreach ($cases as $name => $lines) {
            Livewire::test(PurchaseCreate::class)->call('save', $lines)->assertHasErrors();
            $this->assertEquals(0, (float) Item::find($id)->stock_qty, "Stock changed on: {$name}");
        }
    }

    public function test_purchase_rejects_future_date(): void
    {
        $this->login();
        Livewire::test(PurchaseCreate::class)
            ->set('purchase_date', now()->addDays(3)->toDateString())
            ->call('save', [['item_id' => Item::first()->id, 'qty' => '1', 'unit_price' => '10']])
            ->assertHasErrors('purchase_date');
    }

    public function test_decimal_quantities_are_exact(): void
    {
        $this->login();
        $item = Item::where('name', 'Haldi')->first();

        Livewire::test(PurchaseCreate::class)
            ->call('save', [['item_id' => $item->id, 'qty' => '0.25', 'unit_price' => '880']])
            ->assertHasNoErrors();

        $this->assertEquals(0.25, (float) $item->refresh()->stock_qty);
        $this->assertEquals(220, (float) $item->purchaseItems()->first()->total);
    }

    // --- Bad input on transfer --------------------------------------------

    public function test_transfer_counts_duplicate_rows_against_stock(): void
    {
        $this->login();
        $item = Item::where('name', 'Salt')->first();
        $this->buy($item, 5, 100);

        Livewire::test(TransferCreate::class)
            ->set('branch_id', Branch::first()->id)
            ->call('save', [['item_id' => $item->id, 'qty' => '3', 'rate' => '100'], ['item_id' => $item->id, 'qty' => '3', 'rate' => '100']])
            ->assertHasErrors('lines');

        $this->assertEquals(5, (float) $item->refresh()->stock_qty);
    }

    public function test_transfer_needs_a_real_branch(): void
    {
        $this->login();
        $item = Item::first();
        $this->buy($item, 5, 100);

        Livewire::test(TransferCreate::class)
            ->call('save', [['item_id' => $item->id, 'qty' => '1']])
            ->assertHasErrors('branch_id');

        Livewire::test(TransferCreate::class)
            ->set('branch_id', 99999)
            ->call('save', [['item_id' => $item->id, 'qty' => '1']])
            ->assertHasErrors('branch_id');
    }

    public function test_failed_transfer_saves_nothing(): void
    {
        $this->login();
        $salt = Item::where('name', 'Salt')->first();
        $oil = Item::where('name', 'Frying oil')->first();
        $this->buy($salt, 5, 100);
        $this->buy($oil, 1, 500);

        // Salt is fine, oil is short: the whole transfer must be refused
        Livewire::test(TransferCreate::class)
            ->set('branch_id', Branch::first()->id)
            ->call('save', [['item_id' => $salt->id, 'qty' => '2', 'rate' => '100'], ['item_id' => $oil->id, 'qty' => '3', 'rate' => '500']])
            ->assertHasErrors('lines');

        $this->assertEquals(5, (float) $salt->refresh()->stock_qty);
        $this->assertDatabaseCount('transfers', 0);
    }

    // --- Output safety ----------------------------------------------------

    public function test_item_names_are_escaped(): void
    {
        $this->login();
        $item = Item::create(['category_id' => Item::first()->category_id, 'name' => '<script>alert(1)</script>', 'unit' => 'kg']);

        $this->get('/items')->assertDontSee('<script>alert(1)</script>', false);
        $this->get("/items/{$item->id}")->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/purchases/new')->assertDontSee('<script>alert(1)</script>', false);
    }

    // --- Deletes keep stock consistent --------------------------------------

    public function test_item_with_history_cannot_be_deleted(): void
    {
        $this->login();
        $item = Item::first();
        $this->buy($item, 1, 10);

        Livewire::test(ItemShow::class, ['item' => $item])->call('delete')->assertHasErrors('delete');
        $this->assertModelExists($item);
    }

    public function test_branch_with_transfers_cannot_be_deleted(): void
    {
        $this->login();
        $item = Item::first();
        $branch = Branch::first();
        $this->buy($item, 2, 10);
        app(StockService::class)->recordTransfer(['branch_id' => $branch->id, 'transfer_date' => now()->toDateString()], [
            ['item_id' => $item->id, 'qty' => 1],
        ]);

        Livewire::test(Branches::class)->call('delete', $branch->id)->assertHasErrors('delete');
        $this->assertModelExists($branch);
    }

    public function test_duplicate_item_name_in_same_category_is_blocked(): void
    {
        $this->login();
        $salt = Item::where('name', 'Salt')->first();

        Livewire::test(ItemsIndex::class)
            ->set('name', 'Salt')
            ->set('category_id', $salt->category_id)
            ->set('unit', 'kg')
            ->call('create')
            ->assertHasErrors('name');
    }

    // --- Account ----------------------------------------------------------

    public function test_password_change_needs_current_password(): void
    {
        $user = $this->login();

        Livewire::test(Account::class)
            ->set('current_password', 'wrong')
            ->set('password', 'newpass123')
            ->set('password_confirmation', 'newpass123')
            ->call('savePassword')
            ->assertHasErrors('current_password');

        Livewire::test(Account::class)
            ->set('current_password', 'Test-pass-123')
            ->set('password', 'newpass123')
            ->set('password_confirmation', 'newpass123')
            ->call('savePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    // --- Dates ------------------------------------------------------------

    public function test_dates_follow_pakistan_time(): void
    {
        $this->login();
        // 8pm UTC on 24 Oct is already 1am on 25 Oct in Pakistan
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 20:00:00', 'UTC'));

        Livewire::test(PurchaseCreate::class)->assertSet('purchase_date', '2026-10-25');
    }
}
