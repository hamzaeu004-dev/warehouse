<?php

namespace Tests\Feature;

use App\Livewire\Purchases\Create as PurchaseCreate;
use App\Livewire\Transfers\Create as TransferCreate;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StockFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_pages_need_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_all_pages_load(): void
    {
        $this->actingAs(User::first());
        $item = Item::first();

        foreach (['/', '/items', "/items/{$item->id}", '/purchases', '/purchases/new', '/transfers', '/transfers/new', '/branches', '/account', '/more'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_purchase_adds_stock_and_tracks_price(): void
    {
        $stock = app(StockService::class);
        $oil = Item::where('name', 'Frying oil')->first();

        $stock->recordPurchase(['purchase_date' => now()->toDateString()], [
            ['item_id' => $oil->id, 'qty' => 10, 'unit_price' => 500],
        ]);
        $p2 = $stock->recordPurchase(['purchase_date' => now()->toDateString()], [
            ['item_id' => $oil->id, 'qty' => 10, 'unit_price' => 600],
        ]);

        $oil->refresh();
        $this->assertEquals(20, (float) $oil->stock_qty);
        $this->assertEquals(600, (float) $oil->last_price);
        $this->assertEquals(550, (float) $oil->avg_cost);
        $this->assertEquals(6000, (float) $p2->total);
    }

    public function test_transfer_reduces_stock_and_blocks_overdraw(): void
    {
        $stock = app(StockService::class);
        $salt = Item::where('name', 'Salt')->first();
        $branch = Branch::first();

        $stock->recordPurchase(['purchase_date' => now()->toDateString()], [
            ['item_id' => $salt->id, 'qty' => 5, 'unit_price' => 100],
        ]);

        $transfer = $stock->recordTransfer(['branch_id' => $branch->id, 'transfer_date' => now()->toDateString()], [
            ['item_id' => $salt->id, 'qty' => 2],
        ]);
        $this->assertEquals(3, (float) $salt->refresh()->stock_qty);
        $this->assertEquals(200, (float) $transfer->total_value);

        try {
            $stock->recordTransfer(['branch_id' => $branch->id, 'transfer_date' => now()->toDateString()], [
                ['item_id' => $salt->id, 'qty' => 4],
            ]);
            $this->fail('Overdraw should be blocked');
        } catch (ValidationException) {
        }
        $this->assertEquals(3, (float) $salt->refresh()->stock_qty);

        $stock->deleteTransfer($transfer);
        $this->assertEquals(5, (float) $salt->refresh()->stock_qty);
    }

    public function test_purchase_delete_is_blocked_after_stock_was_sent(): void
    {
        $stock = app(StockService::class);
        $honey = Item::where('name', 'Honey')->first();

        $purchase = $stock->recordPurchase(['purchase_date' => now()->toDateString()], [
            ['item_id' => $honey->id, 'qty' => 2, 'unit_price' => 900],
        ]);
        $stock->recordTransfer(['branch_id' => Branch::first()->id, 'transfer_date' => now()->toDateString()], [
            ['item_id' => $honey->id, 'qty' => 1],
        ]);

        $this->expectException(ValidationException::class);
        $stock->deletePurchase($purchase->fresh());
    }

    public function test_livewire_purchase_and_transfer_screens_save(): void
    {
        $this->actingAs(User::first());
        $chilli = Item::where('name', 'Red chilli powder')->first();

        Livewire::test(PurchaseCreate::class)
            ->set('store_name', 'Metro')
            ->call('save', [['item_id' => $chilli->id, 'qty' => '2.5', 'unit_price' => '1200']])
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertEquals(2.5, (float) $chilli->refresh()->stock_qty);

        Livewire::test(PurchaseCreate::class)
            ->call('save', [])
            ->assertHasErrors('lines');

        Livewire::test(TransferCreate::class)
            ->set('branch_id', Branch::first()->id)
            ->call('save', [['item_id' => $chilli->id, 'qty' => '1', 'rate' => '1200']])
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertEquals(1.5, (float) $chilli->refresh()->stock_qty);

        Livewire::test(PurchaseCreate::class)->call('createItem', 'Soya sauce', 'bottle', null);
        $this->assertDatabaseHas('items', ['name' => 'Soya sauce', 'unit' => 'bottle']);
    }
}
