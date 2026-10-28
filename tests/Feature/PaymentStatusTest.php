<?php

namespace Tests\Feature;

use App\Livewire\Transfers\Index as InvoiceList;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Transfer;
use App\Models\User;
use App\Services\InvoiceStatus;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Transfer $first;

    private Transfer $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::first());
        $this->travelTo(Carbon::parse('2026-10-24 12:00'));

        $stock = app(StockService::class);
        $salt = Item::where('name', 'Salt')->first();
        $this->branch = Branch::first();
        $stock->recordPurchase(['purchase_date' => '2026-10-01'], [['item_id' => $salt->id, 'qty' => 100, 'unit_price' => 50]]);

        // 1,000 on 1 Oct (due same day) and 500 on 20 Oct (7 days to pay, due 27 Oct)
        $this->first = $stock->recordTransfer(['branch_id' => $this->branch->id, 'transfer_date' => '2026-10-01', 'due_days' => 0], [['item_id' => $salt->id, 'qty' => 10, 'rate' => 100]]);
        $this->second = $stock->recordTransfer(['branch_id' => $this->branch->id, 'transfer_date' => '2026-10-20', 'due_days' => 7], [['item_id' => $salt->id, 'qty' => 5, 'rate' => 100]]);
    }

    private function pay(float $amount): void
    {
        $this->branch->payments()->create(['payment_date' => '2026-10-22', 'amount' => $amount, 'method' => 'cash']);
    }

    private function statusOf(Transfer $t): object
    {
        return InvoiceStatus::forInvoice($t);
    }

    public function test_no_payment_means_pending_and_late_ones_are_overdue(): void
    {
        $this->assertSame('pending', $this->statusOf($this->first)->status);
        $this->assertTrue($this->statusOf($this->first)->overdue);
        $this->assertSame(23, $this->statusOf($this->first)->days_overdue);
        $this->assertSame('Overdue', InvoiceStatus::label($this->statusOf($this->first)));

        // Second one is not due until 27 Oct
        $this->assertSame('pending', $this->statusOf($this->second)->status);
        $this->assertFalse($this->statusOf($this->second)->overdue);
        $this->assertSame('Payment pending', InvoiceStatus::label($this->statusOf($this->second)));
    }

    public function test_payments_settle_the_oldest_invoice_first(): void
    {
        $this->pay(1200);

        $this->assertSame('paid', $this->statusOf($this->first)->status);
        $second = $this->statusOf($this->second);
        $this->assertSame('partial', $second->status);
        $this->assertEquals(200, $second->paid);
        $this->assertEquals(300, $second->due);
        $this->assertSame('Partly paid', InvoiceStatus::label($second));

        $this->pay(300);
        $this->assertSame('paid', $this->statusOf($this->second)->status);
    }

    public function test_pages_show_the_status(): void
    {
        $this->pay(1200);

        $this->get("/transfers/{$this->first->id}")->assertSee('Paid')->assertSee('Fully paid by the branch');
        $this->get("/transfers/{$this->second->id}")->assertSee('Partly paid')->assertSee('Rs 300 still to pay')->assertSee('Record payment');
        $this->get("/branches/{$this->branch->id}")->assertSee('Payment pending · 1 invoice');
        $this->get('/branches')->assertSee('Payment pending · Rs 300');
        $this->get('/')->assertSee('Payment pending')->assertSee('1 unpaid invoice');

        $this->pay(300);
        $this->get('/')->assertDontSee('unpaid invoice');
        $this->get("/branches/{$this->branch->id}")->assertSee('All invoices paid');
    }

    public function test_invoice_list_filters(): void
    {
        $this->pay(1000);   // first paid, second still pending

        Livewire::test(InvoiceList::class)
            ->assertSee('Pending (1)')
            ->set('payment', 'unpaid')->assertSee("Invoice #{$this->second->invoice_no}")->assertDontSee("Invoice #{$this->first->invoice_no} ")
            ->set('payment', 'paid')->assertSee("Invoice #{$this->first->invoice_no}");

        $pending = InvoiceStatus::pendingByBranch();
        $this->assertCount(1, $pending);
        $this->assertEquals(500, $pending[0]->due);
        $this->assertSame(1, $pending[0]->invoices);
    }
}
