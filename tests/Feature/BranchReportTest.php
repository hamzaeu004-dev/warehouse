<?php

namespace Tests\Feature;

use App\Livewire\BranchShow;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BranchReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        $this->actingAs(User::first());
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));

        $stock = app(StockService::class);
        $oil = Item::where('name', 'Frying oil')->first();
        $salt = Item::where('name', 'Salt')->first();
        $stock->recordPurchase(['purchase_date' => '2026-09-01'], [
            ['item_id' => $oil->id, 'qty' => 20, 'unit_price' => 500],
            ['item_id' => $salt->id, 'qty' => 10, 'unit_price' => 100],
        ]);

        $this->branch = Branch::first();
        $other = Branch::skip(1)->first();
        $send = fn ($branch, $date, $lines) => $stock->recordTransfer(['branch_id' => $branch->id, 'transfer_date' => $date], $lines);

        // Last month: 2 oil to this branch
        $send($this->branch, '2026-09-15', [['item_id' => $oil->id, 'qty' => 2]]);
        // This month: 3 + 1 oil and 4 salt to this branch, 5 oil to another branch
        $send($this->branch, '2026-10-05', [['item_id' => $oil->id, 'qty' => 3], ['item_id' => $salt->id, 'qty' => 4]]);
        $send($this->branch, '2026-10-10', [['item_id' => $oil->id, 'qty' => 1]]);
        $send($other, '2026-10-10', [['item_id' => $oil->id, 'qty' => 5]]);
    }

    public function test_this_month_totals_per_item(): void
    {
        $page = Livewire::test(BranchShow::class, ['branch' => $this->branch]);

        $page->assertViewHas('totalValue', 2400.0)   // 4 oil x 500 + 4 salt x 100
            ->assertViewHas('transferCount', 2)
            ->assertViewHas('items', function ($items) {
                $oil = $items->firstWhere('name', 'Frying oil');
                $salt = $items->firstWhere('name', 'Salt');

                return $items->count() === 2
                    && (float) $oil->qty === 4.0 && (float) $oil->total === 2000.0
                    && (float) $salt->qty === 4.0 && (float) $salt->total === 400.0
                    && $items->first()->name === 'Frying oil';   // biggest value first
            })
            ->assertSee('Rs 2,400');
    }

    public function test_period_filters(): void
    {
        Livewire::test(BranchShow::class, ['branch' => $this->branch])
            ->set('period', 'last_month')->assertViewHas('totalValue', 1000.0)->assertViewHas('transferCount', 1)
            ->set('period', 'all')->assertViewHas('totalValue', 3400.0)->assertViewHas('transferCount', 3);
    }

    public function test_pages_show_rates_and_totals(): void
    {
        $this->get("/branches/{$this->branch->id}")->assertOk()->assertSee('Delivered')->assertSee('Balance due')->assertSee('Avg rate');

        $transfer = $this->branch->transfers()->whereDate('transfer_date', '2026-10-05')->first();
        $this->get("/transfers/{$transfer->id}")->assertOk()
            ->assertSee('Rate')->assertSee('Total (2 items)')->assertSee('Rs 1,900');

        $this->get("/transfers?branch={$this->branch->id}")->assertOk()
            ->assertSee("Total delivered · {$this->branch->name}")->assertSee('Rs 3,400');

        $this->get('/branches')->assertSee('total delivered Rs 3,400');
    }

    public function test_unknown_branch_is_404(): void
    {
        $this->get('/branches/999')->assertNotFound();
    }
}
