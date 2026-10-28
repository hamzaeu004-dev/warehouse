<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\ProfitReport;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Profit is what the branches pay minus what the stock they got cost.
 * "Stock worth 800 given, branch pays 1,000 = 200 profit."
 */
class ProfitOnPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::first());
        $this->travelTo(Carbon::parse('2026-10-24 12:00'));

        $stock = app(StockService::class);
        $salt = Item::where('name', 'Salt')->first();
        $this->branch = Branch::first();

        // Stock that cost 800 (10 kg x 80) goes to the branch, invoiced at 1,000
        $stock->recordPurchase(['purchase_date' => '2026-10-19'], [['item_id' => $salt->id, 'qty' => 10, 'unit_price' => 80]]);
        $stock->recordTransfer(['branch_id' => $this->branch->id, 'transfer_date' => '2026-10-20'], [['item_id' => $salt->id, 'qty' => 10, 'rate' => 100]]);
    }

    private function pay(string $date, float $amount): void
    {
        $this->branch->payments()->create(['payment_date' => $date, 'amount' => $amount, 'method' => 'cash']);
    }

    public function test_the_800_and_1000_example(): void
    {
        $this->pay('2026-10-22', 1000);

        $week = ProfitReport::totals(...ProfitReport::range('week', now()));
        $this->assertEquals(1000, $week['received']);
        $this->assertEquals(800, $week['cost']);
        $this->assertEquals(200, $week['cash_profit']);
        $this->assertEquals(20.0, $week['cash_margin']);
    }

    public function test_unpaid_stock_shows_as_loss_until_paid(): void
    {
        $week = ProfitReport::totals(...ProfitReport::range('week', now()));
        $this->assertEquals(-800, $week['cash_profit']);   // gave stock, no money yet
        $this->assertEquals(200, $week['net_profit']);     // on invoices it is already 200

        $this->get("/branches/{$this->branch->id}")
            ->assertSee('Loss so far')->assertSee('Rs 800')
            ->assertSee('If it pays the full balance, profit will be Rs 200');
    }

    public function test_part_payment_and_payment_next_month(): void
    {
        $this->pay('2026-10-23', 600);                       // October: 600 in, 800 out
        $this->travelTo(Carbon::parse('2026-11-05 12:00'));
        $this->pay('2026-11-03', 400);                       // November: rest of the money

        $oct = ProfitReport::totals(...ProfitReport::range('month', Carbon::parse('2026-10-15')));
        $nov = ProfitReport::totals(...ProfitReport::range('month', Carbon::parse('2026-11-15')));
        $year = ProfitReport::totals(...ProfitReport::range('year', now()));

        $this->assertEquals(-200, $oct['cash_profit']);
        $this->assertEquals(400, $nov['cash_profit']);
        $this->assertEquals(200, $year['cash_profit']);    // evens out over the year
    }

    public function test_branch_paying_more_than_invoiced_counts_as_profit(): void
    {
        $this->pay('2026-10-22', 1200);

        $byBranch = ProfitReport::byBranch(...ProfitReport::range('week', now()));
        $row = $byBranch->firstWhere('id', $this->branch->id);
        $this->assertEquals(1200, $row->received);
        $this->assertEquals(400, $row->received - $row->cost);
        $this->assertEquals(-200, $this->branch->balanceDue());   // paid in advance

        $this->get("/branches/{$this->branch->id}")->assertSee('Paid in advance')->assertSee('Profit so far');
    }

    public function test_wastage_is_taken_off(): void
    {
        $this->pay('2026-10-22', 1000);
        $oil = Item::where('name', 'Frying oil')->first();
        app(StockService::class)->recordPurchase(['purchase_date' => '2026-10-19'], [['item_id' => $oil->id, 'qty' => 5, 'unit_price' => 50]]);
        app(StockService::class)->recordAdjustment(['adjustment_date' => '2026-10-23', 'reason' => 'wastage'], [['item_id' => $oil->id, 'qty' => 1]]);

        $week = ProfitReport::totals(...ProfitReport::range('week', now()));
        $this->assertEquals(150, $week['cash_profit']);    // 1,000 - 800 - 50
    }
}
