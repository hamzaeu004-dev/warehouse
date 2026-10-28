<?php

namespace Tests\Feature;

use App\Livewire\Transfers\Create;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Transfer;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rebuilds the real Roosters Raheem Garden invoice #19016 (19 Oct 2026)
 * and checks every number matches the PDF, except the PDF's Kgs total:
 * it says 214.25 although 144 + 69 + 12.25 = 225.25.
 */
class TransferInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private array $chicken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::first());
        $this->travelTo(now()->setDate(2026, 10, 20));

        $this->chicken = [
            '8pc' => Item::where('name', 'SKIN ON 8 PCS CUT (2.3 - 2.4 GM) (R)')->firstOrFail(),
            '2pc' => Item::where('name', 'SKIN ON 2 PC CUT 2.3 KG')->firstOrFail(),
            'zinger' => Item::where('name', 'ZINGER - (110- 130) GM')->firstOrFail(),
        ];
        app(StockService::class)->recordPurchase(['purchase_date' => '2026-10-18'], [
            ['item_id' => $this->chicken['8pc']->id, 'qty' => 200, 'unit_price' => 600],
            ['item_id' => $this->chicken['2pc']->id, 'qty' => 100, 'unit_price' => 650],
            ['item_id' => $this->chicken['zinger']->id, 'qty' => 50, 'unit_price' => 950],
        ]);

        $this->branch = Branch::first();
        $this->branch->update(['name' => 'ROOSTERS - RAHEEM GARDEN', 'contact_person' => 'Abdul Mateen', 'address' => 'Raheem Garden G.T Road']);
    }

    private function sendInvoice19016()
    {
        return Livewire::test(Create::class)
            ->set('branch_id', $this->branch->id)
            ->set('invoice_no', '19016')
            ->set('transfer_date', '2026-10-19')
            ->set('due_days', '0')
            ->call('save', [
                ['item_id' => $this->chicken['8pc']->id, 'qty' => '144', 'pieces' => '60', 'rate' => '669.20', 'description' => ''],
                ['item_id' => $this->chicken['2pc']->id, 'qty' => '69', 'pieces' => '30', 'rate' => '706.10', 'description' => ''],
                ['item_id' => $this->chicken['zinger']->id, 'qty' => '12.25', 'pieces' => '5', 'rate' => '1019.75', 'description' => ''],
            ]);
    }

    public function test_amounts_match_the_pdf(): void
    {
        $this->sendInvoice19016()->assertHasNoErrors()->assertRedirect();

        $transfer = Transfer::where('invoice_no', 19016)->firstOrFail();
        $this->assertEquals([96365, 48721, 12492], $transfer->lines->pluck('total')->map(fn ($t) => (float) $t)->all());
        $this->assertEquals(157578, (float) $transfer->total_value);
        $this->assertEquals([60, 30, 5], $transfer->lines->pluck('pieces')->map(fn ($p) => (float) $p)->all());

        // Stock goes down by the Kgs
        $this->assertEquals(56, (float) $this->chicken['8pc']->refresh()->stock_qty);
        $this->assertEquals(31, (float) $this->chicken['2pc']->refresh()->stock_qty);
        $this->assertEquals(37.75, (float) $this->chicken['zinger']->refresh()->stock_qty);
        // Invoice rate does not change the warehouse purchase cost
        $this->assertEquals(600, (float) $this->chicken['8pc']->avg_cost);
    }

    public function test_printable_invoice_matches_the_pdf(): void
    {
        $this->sendInvoice19016();
        $transfer = Transfer::where('invoice_no', 19016)->firstOrFail();

        $this->get("/transfers/{$transfer->id}/invoice")->assertOk()->assertSeeInOrder([
            'Commercial Invoice',
            'Invoice No:', '19016', 'Issue Date:', '19 Oct 2026', 'Amount Payable:', 'Rs.157,578',
            'Due Date:', '19 Oct 2026', 'Terms:', '0 Days.', 'STI #', '19016',
            'Deliver To', 'ATTN:', 'ABDUL MATEEN', 'ROOSTERS - RAHEEM GARDEN', 'RAHEEM GARDEN G.T ROAD',
            'SKIN ON 8 PCS CUT (2.3 - 2.4 GM) (R)', '144.00', '60', '669.20', 'Rs. 96,365.00',
            'SKIN ON 2 PC CUT 2.3 KG', '69.00', '30', '706.10', 'Rs. 48,721.00',
            'ZINGER - (110- 130) GM', '12.25', '5', '1,019.75', 'Rs. 12,492.00',
            'Rupees One Hundred Fifty-Seven Thousand Five Hundred Seventy-Eight Only.', '225.25', '95', 'Total', 'Rs. 157,578.00',
        ], false);
    }

    public function test_invoice_number_rules(): void
    {
        $this->sendInvoice19016();

        // Next invoice number is suggested automatically
        Livewire::test(Create::class)->assertSet('invoice_no', '19017');

        // Same number cannot be used twice
        $this->sendInvoice19016()->assertHasErrors('invoice_no');
        $this->assertSame(1, Transfer::where('invoice_no', 19016)->count());
    }

    public function test_rate_is_required_and_stock_is_checked(): void
    {
        Livewire::test(Create::class)
            ->set('branch_id', $this->branch->id)
            ->call('save', [['item_id' => $this->chicken['zinger']->id, 'qty' => '5', 'rate' => '']])
            ->assertHasErrors('lines.0.rate');

        Livewire::test(Create::class)
            ->set('branch_id', $this->branch->id)
            ->call('save', [['item_id' => $this->chicken['zinger']->id, 'qty' => '51', 'rate' => '1000']])
            ->assertHasErrors('lines');

        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_deleting_invoice_returns_stock_without_changing_cost(): void
    {
        $this->sendInvoice19016();
        $transfer = Transfer::where('invoice_no', 19016)->firstOrFail();

        app(StockService::class)->deleteTransfer($transfer);

        $this->assertEquals(200, (float) $this->chicken['8pc']->refresh()->stock_qty);
        $this->assertEquals(600, (float) $this->chicken['8pc']->avg_cost);
    }

    public function test_amount_in_words(): void
    {
        $this->assertSame('Rupees One Hundred Fifty-Seven Thousand Five Hundred Seventy-Eight Only', rupees_in_words(157578));
        $this->assertSame('Rupees Ten Only', rupees_in_words(10));
        $this->assertSame('Rupees One Million Two Hundred Thousand Fifteen Only', rupees_in_words(1200015));
        $this->assertSame('Rupees Zero Only', rupees_in_words(0));
    }
}
