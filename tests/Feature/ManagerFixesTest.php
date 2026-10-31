<?php

namespace Tests\Feature;

use App\Livewire\BranchShow;
use App\Livewire\OrderList;
use App\Livewire\Purchases\Create as PurchaseCreate;
use App\Livewire\Purchases\Pickable;
use App\Livewire\Transfers\Create as TransferCreate;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Transfer;
use App\Models\User;
use App\Services\OrderPlanner;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The gaps found when using the app as the warehouse manager for a week.
 */
class ManagerFixesTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private Item $salt;

    private Item $chicken;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        $this->actingAs(User::first());
        $this->travelTo(Carbon::parse('2026-10-24 12:00'));   // Saturday; week = 19–25 Oct

        $this->stock = app(StockService::class);
        $this->salt = Item::where('name', 'Salt')->first();
        $this->chicken = Item::where('name', 'SKIN ON 8 PCS CUT (2.3 - 2.4 GM) (R)')->first();
        $this->branch = Branch::first();
    }

    private function buy(): Purchase
    {
        return $this->stock->recordPurchase(['purchase_date' => '2026-10-19', 'store_name' => 'Metro'], [
            ['item_id' => $this->salt->id, 'qty' => 10, 'unit_price' => 60],
            ['item_id' => $this->chicken->id, 'qty' => 300, 'pieces' => 125, 'unit_price' => 600],
        ]);
    }

    private function send(float $chickenKg = 144): Transfer
    {
        return $this->stock->recordTransfer(['branch_id' => $this->branch->id, 'transfer_date' => '2026-10-20', 'invoice_no' => 19016], [
            ['item_id' => $this->chicken->id, 'qty' => $chickenKg, 'pieces' => 60, 'rate' => 669.20],
            ['item_id' => $this->salt->id, 'qty' => 3, 'rate' => 80],
        ]);
    }

    // --- 1. Editing a purchase -----------------------------------------------

    public function test_wrong_price_can_be_fixed_after_stock_was_sent(): void
    {
        $purchase = $this->buy();
        $this->send();

        Livewire::test(PurchaseCreate::class, ['purchase' => $purchase])
            ->assertSet('store_name', 'Metro')
            ->call('save', [
                ['item_id' => $this->salt->id, 'qty' => '10', 'unit_price' => '65'],
                ['item_id' => $this->chicken->id, 'qty' => '300', 'pieces' => '125', 'unit_price' => '600'],
            ])
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->salt->refresh();
        $this->assertEquals(7, (float) $this->salt->stock_qty);          // stock unchanged
        $this->assertEquals(65, (float) $this->salt->last_price);
        // 7 kg left; the extra Rs 5 x 10 kg is now carried by those 7 kg
        $this->assertEquals(round((7 * 60 + 50) / 7, 2), (float) $this->salt->avg_cost);
        $this->assertEquals(180650, (float) $purchase->fresh()->total);
        $this->assertStringContainsString('Salt: rate 60 → 65', ActivityLog::latest('id')->first()->description);
    }

    public function test_qty_can_drop_only_by_what_is_still_in_the_warehouse(): void
    {
        $purchase = $this->buy();
        $this->send();   // 156 kg chicken left of 300

        Livewire::test(PurchaseCreate::class, ['purchase' => $purchase])
            ->call('save', [
                ['item_id' => $this->salt->id, 'qty' => '10', 'unit_price' => '60'],
                ['item_id' => $this->chicken->id, 'qty' => '100', 'unit_price' => '600'],
            ])
            ->assertHasErrors('lines');
        $this->assertEquals(156, (float) $this->chicken->fresh()->stock_qty);

        Livewire::test(PurchaseCreate::class, ['purchase' => $purchase])
            ->call('save', [
                ['item_id' => $this->salt->id, 'qty' => '10', 'unit_price' => '60'],
                ['item_id' => $this->chicken->id, 'qty' => '290', 'pieces' => '120', 'unit_price' => '600'],
            ])
            ->assertHasNoErrors();
        $this->assertEquals(146, (float) $this->chicken->fresh()->stock_qty);
        $this->assertEquals(120, (float) $purchase->fresh()->lines->firstWhere('item_id', $this->chicken->id)->pieces);
    }

    public function test_edit_form_gets_plain_numbers_for_big_quantities(): void
    {
        // "1,000" was read as 1 by the browser
        $glass = Item::where('name', 'Drink glass')->first();
        $purchase = $this->stock->recordPurchase(['purchase_date' => '2026-10-19'], [
            ['item_id' => $glass->id, 'qty' => 1000, 'unit_price' => 6],
        ]);

        Livewire::test(PurchaseCreate::class, ['purchase' => $purchase])
            ->assertViewHas('initialRows', fn ($rows) => $rows[0]['qty'] === 1000.0);

        $glass->update(['min_stock' => 1500]);
        Livewire::test(\App\Livewire\Items\Show::class, ['item' => $glass])
            ->call('edit')->assertSet('min_stock', '1500')->call('save')->assertHasNoErrors();
    }

    public function test_purchase_pages_have_edit(): void
    {
        $purchase = $this->buy();

        $this->get("/purchases/{$purchase->id}")->assertSee(route('purchases.edit', $purchase))->assertSee('125 pcs');
        $this->get("/purchases/{$purchase->id}/edit")->assertOk()->assertSee('You are correcting purchase');
    }

    // --- 1b. Editing an invoice ----------------------------------------------

    public function test_invoice_qty_and_rate_can_be_corrected(): void
    {
        $this->buy();
        $invoice = $this->send(144);

        Livewire::test(TransferCreate::class, ['transfer' => $invoice])
            ->assertSet('invoice_no', '19016')
            ->assertSet('branch_id', $this->branch->id)
            ->call('save', [
                ['item_id' => $this->chicken->id, 'qty' => '140', 'pieces' => '58', 'rate' => '670'],
                ['item_id' => $this->salt->id, 'qty' => '3', 'rate' => '80'],
            ])
            ->assertHasNoErrors();

        $invoice->refresh();
        $this->assertEquals(160, (float) $this->chicken->fresh()->stock_qty);   // 4 kg came back
        $this->assertEquals(93800 + 240, (float) $invoice->total_value);
        $this->assertEquals(600, (float) $invoice->lines->firstWhere('item_id', $this->chicken->id)->cost_price);
        $this->assertSame(1, Transfer::count());
    }

    public function test_invoice_edit_respects_stock_and_unique_number(): void
    {
        $this->buy();
        $invoice = $this->send(144);
        $other = $this->stock->recordTransfer(['branch_id' => $this->branch->id, 'transfer_date' => '2026-10-21'], [
            ['item_id' => $this->salt->id, 'qty' => 1, 'rate' => 80],
        ]);

        // 156 left + 144 already on it = at most 300
        Livewire::test(TransferCreate::class, ['transfer' => $invoice])
            ->call('save', [['item_id' => $this->chicken->id, 'qty' => '301', 'rate' => '669.20']])
            ->assertHasErrors('lines');

        Livewire::test(TransferCreate::class, ['transfer' => $other])
            ->set('invoice_no', '19016')
            ->call('save', [['item_id' => $this->salt->id, 'qty' => '1', 'rate' => '80']])
            ->assertHasErrors('invoice_no');
    }

    // --- 2. Selling rate ----------------------------------------------------

    public function test_selling_rate_is_offered_to_invoices(): void
    {
        $this->chicken->update(['sale_rate' => 669.20]);

        $picked = collect(Pickable::all())->firstWhere('id', $this->chicken->id);
        $this->assertSame(669.2, $picked['sale_rate']);

        $this->get("/items/{$this->chicken->id}")->assertSee('Rs 669.20 / kg');
    }

    // --- 3. Weekly order list -------------------------------------------------

    public function test_order_list_from_last_weeks_use(): void
    {
        // Last week (12–18 Oct): 50 kg chicken sent, 2 kg wasted; now 20 kg in stock
        $this->stock->recordPurchase(['purchase_date' => '2026-10-12'], [['item_id' => $this->chicken->id, 'qty' => 72, 'unit_price' => 600]]);
        $this->stock->recordTransfer(['branch_id' => $this->branch->id, 'transfer_date' => '2026-10-14'], [['item_id' => $this->chicken->id, 'qty' => 50, 'rate' => 669]]);
        $this->stock->recordAdjustment(['adjustment_date' => '2026-10-15', 'reason' => 'wastage'], [['item_id' => $this->chicken->id, 'qty' => 2]]);
        $this->salt->update(['min_stock' => 5]);

        $rows = OrderPlanner::suggestions('last_week')->keyBy(fn ($r) => $r->item->name);

        $this->assertEquals(52, $rows[$this->chicken->name]->weekly_use);
        $this->assertEquals(32, $rows[$this->chicken->name]->suggested);   // 52 - 20
        $this->assertEquals(5, $rows['Salt']->suggested);                  // min stock only
        $this->assertFalse($rows->has('Honey'));                           // not used, no minimum

        $this->get('/order-list')->assertOk()->assertSee('What to buy this week');
        Livewire::test(OrderList::class)->set('basis', 'avg4')->assertOk();
    }

    // --- 4. Branch payments ---------------------------------------------------

    public function test_payments_reduce_balance_due(): void
    {
        $this->buy();
        $this->send();   // 144 x 669.20 = 96,365 + 3 x 80 = 240
        $this->assertEquals(96605, $this->branch->balanceDue());

        Livewire::test(BranchShow::class, ['branch' => $this->branch])
            ->call('openPaymentForm')
            ->set('payAmount', '50000')
            ->set('payMethod', 'bank')
            ->set('payReference', 'TID 123')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $this->assertEquals(46605, $this->branch->balanceDue());
        $this->assertStringContainsString('Balance due now Rs 46,605', ActivityLog::latest('id')->first()->description);
        $this->get("/branches/{$this->branch->id}")->assertSee('Balance due')->assertSee('Rs 46,605')->assertSee('TID 123');
        $this->get('/')->assertSee('To collect')->assertSee('Rs 46,605');

        Livewire::test(BranchShow::class, ['branch' => $this->branch])
            ->call('openPaymentForm')->set('payAmount', '-5')->call('recordPayment')->assertHasErrors('payAmount');
    }

    // --- 5. Pages render with the new design ---------------------------------

    public function test_all_pages_load(): void
    {
        $purchase = $this->buy();
        $invoice = $this->send();

        foreach (['/', '/more', '/order-list', '/analysis', '/items', "/items/{$this->chicken->id}", '/purchases/new',
            "/purchases/{$purchase->id}/edit", "/transfers/{$invoice->id}/edit", "/branches/{$this->branch->id}", '/branches', '/categories'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
