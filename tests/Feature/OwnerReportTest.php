<?php

namespace Tests\Feature;

use App\Livewire\Reports;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\OwnerReport;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
        $this->actingAs(User::first());
        $this->travelTo(Carbon::parse('2026-10-24 12:00'));

        $stock = app(StockService::class);
        $oil = Item::where('name', 'Frying oil')->first();
        $salt = Item::where('name', 'Salt')->first();
        $branches = Branch::orderBy('id')->get();

        // October: buy 20 L oil @500 and 10 kg salt @60 = 10,600
        $stock->recordPurchase(['purchase_date' => '2026-10-05', 'store_name' => 'Metro'], [
            ['item_id' => $oil->id, 'qty' => 20, 'unit_price' => 500],
            ['item_id' => $salt->id, 'qty' => 10, 'unit_price' => 60],
        ]);
        // Deliver 5 L oil @600 to branch 1 (3,000) and 2 kg salt @80 to branch 2 (160)
        $stock->recordTransfer(['branch_id' => $branches[0]->id, 'transfer_date' => '2026-10-10'], [['item_id' => $oil->id, 'qty' => 5, 'rate' => 600]]);
        $stock->recordTransfer(['branch_id' => $branches[1]->id, 'transfer_date' => '2026-10-11'], [['item_id' => $salt->id, 'qty' => 2, 'rate' => 80]]);
        // Wastage: 1 L oil spilled (500), 1 kg salt expired (60)
        $stock->recordAdjustment(['adjustment_date' => '2026-10-12', 'reason' => 'wastage'], [['item_id' => $oil->id, 'qty' => 1]]);
        $stock->recordAdjustment(['adjustment_date' => '2026-10-13', 'reason' => 'expired'], [['item_id' => $salt->id, 'qty' => 1]]);

        // September purchase, outside October
        $stock->recordPurchase(['purchase_date' => '2026-09-20'], [['item_id' => $salt->id, 'qty' => 5, 'unit_price' => 60]]);
    }

    public function test_october_figures(): void
    {
        $r = OwnerReport::build(...OwnerReport::range('this_month'));
        $s = $r['summary'];

        $this->assertEquals(10600, $s['purchased']);
        $this->assertSame(1, $s['purchase_count']);
        $this->assertEquals(3160, $s['delivered']);
        $this->assertSame(2, $s['invoice_count']);
        $this->assertEquals(560, $s['wastage']);
        $this->assertCount(2, $r['deliveredByBranch']);
        $this->assertEquals(['Frying oil', 'Salt'], $r['purchasedItems']->pluck('name')->all());
        $this->assertEquals(['Wastage', 'Expired'], $r['wastage']->pluck('reason_label')->all());
    }

    public function test_custom_and_last_month_ranges(): void
    {
        $sept = OwnerReport::build(...OwnerReport::range('last_month'));
        $this->assertEquals(300, $sept['summary']['purchased']);
        $this->assertEquals(0, $sept['summary']['delivered']);

        [$from, $to] = OwnerReport::range('custom', '2026-10-11', '2026-10-12');
        $two = OwnerReport::build($from, $to)['summary'];
        $this->assertEquals(160, $two['delivered']);
        $this->assertEquals(500, $two['wastage']);
        $this->assertEquals(0, $two['purchased']);

        // Dates the wrong way round are swapped, not an error
        [$a, $b] = OwnerReport::range('custom', '2026-10-12', '2026-10-11');
        $this->assertTrue($a->lt($b));
    }

    public function test_pdf_downloads(): void
    {
        $response = $this->get('/reports/pdf?period=this_month');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename=Warehouse-report_01-Oct-2026_to_24-Oct-2026.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->get('/reports/pdf?period=this_month&view=1')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/reports/pdf?period=nonsense')->assertSessionHasErrors('period');
    }

    public function test_pdf_contents(): void
    {
        $html = view('reports.owner-pdf', OwnerReport::build(...OwnerReport::range('this_month')))->render();

        foreach (['Warehouse Report', '01 Oct 2026 – 24 Oct 2026', '1. Purchasing', 'Rs 10,600', '2. Delivered to branches', 'Rs 3,160', '3. Wastage', 'Rs 560', 'Metro'] as $text) {
            $this->assertStringContainsString($text, $html, "Missing: {$text}");
        }
    }

    public function test_page_and_login(): void
    {
        $this->get('/reports')->assertOk()->assertSee('Download PDF')->assertSee('Rs 10,600');
        Livewire::test(Reports::class)->call('setPeriod', 'last_month')->assertSee('Rs 300');

        auth()->logout();
        $this->get('/reports')->assertRedirect('/login');
        $this->get('/reports/pdf')->assertRedirect('/login');
    }
}
