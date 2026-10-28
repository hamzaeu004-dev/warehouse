<?php

namespace Tests\Feature;

use App\Livewire\Analysis;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\ProfitReport;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::first());
        // Saturday 24 Oct 2026; with weeks starting Monday this week is 19–25 Oct
        $this->travelTo(Carbon::parse('2026-10-24 12:00'));

        $stock = app(StockService::class);
        $zinger = Item::where('name', 'ZINGER - (110- 130) GM')->first();
        $oil = Item::where('name', 'Frying oil')->first();
        $branch = Branch::first();

        // Last week (12–18 Oct): buy 50 kg zinger at 900, sell 10 kg at 1000
        $stock->recordPurchase(['purchase_date' => '2026-10-12'], [['item_id' => $zinger->id, 'qty' => 50, 'unit_price' => 900]]);
        $stock->recordTransfer(['branch_id' => $branch->id, 'transfer_date' => '2026-10-14'], [['item_id' => $zinger->id, 'qty' => 10, 'rate' => 1000]]);

        // This week: buy 20 L oil at 500 on Monday, sell 20 kg zinger at 1019.75 and 4 L oil at 600 on Tuesday,
        // and 1 kg zinger goes bad on Saturday
        $stock->recordPurchase(['purchase_date' => '2026-10-19'], [['item_id' => $oil->id, 'qty' => 20, 'unit_price' => 500]]);
        $stock->recordTransfer(['branch_id' => $branch->id, 'transfer_date' => '2026-10-20'], [
            ['item_id' => $zinger->id, 'qty' => 20, 'rate' => 1019.75],
            ['item_id' => $oil->id, 'qty' => 4, 'rate' => 600],
        ]);
        $stock->recordAdjustment(['adjustment_date' => '2026-10-24', 'reason' => 'wastage'], [['item_id' => $zinger->id, 'qty' => 1]]);
    }

    public function test_this_week(): void
    {
        $t = ProfitReport::totals(...ProfitReport::range('week', now()));

        $this->assertEquals(10000, $t['investment']);      // 20 x 500
        $this->assertEquals(22795, $t['sales']);           // 20 x 1019.75 = 20395 + 4 x 600 = 2400
        $this->assertEquals(20000, $t['cost']);            // 20 x 900 + 4 x 500
        $this->assertEquals(2795, $t['gross_profit']);
        $this->assertEquals(-900, $t['adjustments']);      // 1 kg zinger at cost 900
        $this->assertEquals(1895, $t['net_profit']);
        $this->assertEquals(12.3, $t['margin']);
    }

    public function test_last_week_day_month_year(): void
    {
        $lastWeek = ProfitReport::totals(...ProfitReport::range('week', now()->subWeek()));
        $this->assertEquals([45000, 10000, 1000], [$lastWeek['investment'], $lastWeek['sales'], $lastWeek['net_profit']]);

        $tuesday = ProfitReport::totals(...ProfitReport::range('day', Carbon::parse('2026-10-20')));
        $this->assertEquals([0, 22795, 2795], [$tuesday['investment'], $tuesday['sales'], $tuesday['net_profit']]);

        $month = ProfitReport::totals(...ProfitReport::range('month', now()));
        $this->assertEquals([55000, 32795, 2895], [$month['investment'], $month['sales'], $month['net_profit']]);

        $lastYear = ProfitReport::totals(...ProfitReport::range('year', now()->subYear()));
        $this->assertEquals(0, $lastYear['net_profit']);
        $this->assertNull($lastYear['margin']);
    }

    public function test_week_start_day_is_configurable(): void
    {
        config(['inventory.week_starts_on' => 'saturday']);
        [$from, $to] = ProfitReport::range('week', now());

        $this->assertSame('2026-10-24', $from->toDateString());
        $this->assertSame('2026-10-30', $to->toDateString());
    }

    public function test_series_has_one_row_per_period(): void
    {
        $weeks = ProfitReport::series('week', now(), 8);

        $this->assertCount(8, $weeks);
        $this->assertSame('2026-10-19', $weeks[7]['from']->toDateString());
        $this->assertEquals(1895, $weeks[7]['net_profit']);
        $this->assertEquals(1000, $weeks[6]['net_profit']);
    }

    public function test_page_and_navigation(): void
    {
        // Default: profit on money received. No payments yet, so this week is a loss of
        // the stock cost (20,000) plus the wasted zinger (900)
        $this->get('/analysis')->assertOk()->assertSee('Loss · on money received')->assertSee('Rs 20,900');

        // Branch pays 25,000 on Thursday: 25,000 - 20,000 - 900 = 4,100 profit
        Branch::first()->payments()->create(['payment_date' => '2026-10-22', 'amount' => 25000, 'method' => 'cash']);
        $this->get('/analysis')->assertSee('Profit · on money received')->assertSee('Rs 4,100')->assertSee('Investment vs money received');
        $this->get('/')->assertSee('Profit this week')->assertSee('Rs 4,100');

        // The invoice view is still there
        Livewire::test(Analysis::class)->call('setBasis', 'invoice')->assertSee('Profit · on invoices')->assertSee('Rs 1,895');

        Livewire::test(Analysis::class)->call('setBasis', 'invoice')
            ->call('previous')->assertSet('date', '2026-10-17')->assertSee('Rs 1,000')
            ->call('next')->call('next')->assertSet('date', '2026-10-24')   // cannot go past this week
            ->call('setPeriod', 'month')->assertSee('October 2026')->assertSee('Rs 2,895')
            ->call('setPeriod', 'year')->assertSee('2026')
            ->call('setPeriod', 'day')->assertSee('Saturday, 24 Oct 2026')->assertSee('Net loss');

        // Payments show up per day, month and year too
        Livewire::test(Analysis::class)->call('setPeriod', 'month')->assertSee('Rs 25,000');
    }

    public function test_page_needs_login(): void
    {
        auth()->logout();
        $this->get('/analysis')->assertRedirect('/login');
    }
}
