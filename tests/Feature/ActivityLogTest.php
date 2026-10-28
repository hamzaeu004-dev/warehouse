<?php

namespace Tests\Feature;

use App\Livewire\Account;
use App\Livewire\ActivityLogPage;
use App\Livewire\Branches;
use App\Livewire\Items\Index as ItemsIndex;
use App\Livewire\Items\Show as ItemShow;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function latest(): ActivityLog
    {
        return ActivityLog::latest('id')->first();
    }

    public function test_login_logout_and_failed_login_are_logged(): void
    {
        $this->post('/login', ['email' => 'manager@inventory.test', 'password' => 'wrong']);
        $this->assertSame('account.login_failed', $this->latest()->action);
        $this->assertStringContainsString('manager@inventory.test', $this->latest()->description);

        $this->post('/login', ['email' => 'manager@inventory.test', 'password' => 'Test-pass-123'], ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14)']);
        $this->assertSame('account.login', $this->latest()->action);
        $this->assertStringContainsString('Android phone', $this->latest()->description);
        $this->assertNotNull($this->latest()->user_id);

        $this->post('/logout');
        $this->assertSame('account.logout', $this->latest()->action);
    }

    public function test_stock_actions_are_logged_with_item_lines(): void
    {
        $this->actingAs(User::first());
        $stock = app(StockService::class);
        $oil = Item::where('name', 'Frying oil')->first();
        $branch = Branch::first();

        $purchase = $stock->recordPurchase(['purchase_date' => now()->toDateString(), 'store_name' => 'Metro'], [
            ['item_id' => $oil->id, 'qty' => 10, 'unit_price' => 500],
        ]);
        $log = $this->latest();
        $this->assertSame('purchase.created', $log->action);
        $this->assertSame("Purchase #{$purchase->id} added: 1 item, Rs 5,000 from Metro", $log->description);
        $this->assertSame([['name' => 'Frying oil', 'qty' => 10, 'unit' => 'litre', 'rate' => 500, 'total' => 5000]], $log->properties['lines']);

        $transfer = $stock->recordTransfer(['branch_id' => $branch->id, 'transfer_date' => now()->toDateString()], [
            ['item_id' => $oil->id, 'qty' => 2],
        ]);
        $this->assertSame("Invoice #{$transfer->invoice_no}: sent 1 item to {$branch->name}, amount Rs 1,000", $this->latest()->description);

        $adjustment = $stock->recordAdjustment(['adjustment_date' => now()->toDateString(), 'reason' => 'wastage', 'notes' => 'Spilled'], [
            ['item_id' => $oil->id, 'qty' => 1],
        ]);
        $this->assertSame('Stock adjusted (Wastage): 1 item, loss Rs 500. Note: Spilled', $this->latest()->description);

        $stock->deleteAdjustment($adjustment);
        $this->assertSame('adjustment.deleted', $this->latest()->action);

        $stock->deleteTransfer($transfer->fresh());
        $this->assertSame('transfer.deleted', $this->latest()->action);

        $stock->deletePurchase($purchase->fresh());
        $this->assertSame('purchase.deleted', $this->latest()->action);
        $this->assertCount(1, $this->latest()->properties['lines']);
    }

    public function test_failed_stock_action_is_not_logged(): void
    {
        $this->actingAs(User::first());
        $before = ActivityLog::count();

        try {
            app(StockService::class)->recordTransfer(['branch_id' => Branch::first()->id, 'transfer_date' => now()->toDateString()], [
                ['item_id' => Item::first()->id, 'qty' => 5],
            ]);
        } catch (\Illuminate\Validation\ValidationException) {
        }

        $this->assertSame($before, ActivityLog::count());
    }

    public function test_item_branch_and_account_changes_are_logged(): void
    {
        $this->actingAs(User::first());
        $salt = Item::where('name', 'Salt')->first();

        Livewire::test(ItemsIndex::class)->set('name', 'Soya sauce')->set('category_id', $salt->category_id)->set('unit', 'bottle')->call('create');
        $this->assertStringStartsWith('New item added: Soya sauce', $this->latest()->description);

        Livewire::test(ItemShow::class, ['item' => $salt])->call('edit')->set('unit', 'packet')->set('min_stock', '5')->call('save');
        $this->assertSame('Item updated: Salt. unit kg → packet, min stock none → 5', $this->latest()->description);

        Livewire::test(Branches::class)->call('edit', Branch::first()->id)->set('editName', 'Gulberg')->call('update');
        $this->assertSame('Branch renamed: Branch 1 → Gulberg', $this->latest()->description);

        Livewire::test(Account::class)->set('current_password', 'Test-pass-123')->set('password', 'newpass123')->set('password_confirmation', 'newpass123')->call('savePassword');
        $this->assertSame('Password changed', $this->latest()->description);
    }

    public function test_saving_without_changes_logs_nothing(): void
    {
        $this->actingAs(User::first());
        $before = ActivityLog::count();

        Livewire::test(ItemShow::class, ['item' => Item::first()])->call('edit')->call('save');

        $this->assertSame($before, ActivityLog::count());
    }

    public function test_log_page_shows_filters_and_links(): void
    {
        $this->actingAs(User::first());
        $stock = app(StockService::class);
        $oil = Item::where('name', 'Frying oil')->first();
        $kept = $stock->recordPurchase(['purchase_date' => now()->toDateString()], [['item_id' => $oil->id, 'qty' => 1, 'unit_price' => 10]]);
        $gone = $stock->recordPurchase(['purchase_date' => now()->toDateString()], [['item_id' => $oil->id, 'qty' => 1, 'unit_price' => 10]]);
        $stock->deletePurchase($gone->fresh());
        ActivityLog::create(['action' => 'account.login', 'description' => 'Logged in from IP 1.2.3.4']);

        $this->get('/activity')->assertOk()->assertSee('Today')->assertSee("Purchase #{$kept->id} added")->assertSee(route('purchases.show', $kept));
        // Deleted records are not linked
        $this->get('/activity')->assertDontSee(route('purchases.show', $gone));

        Livewire::test(ActivityLogPage::class)->set('group', 'purchase')
            ->assertSee("Purchase #{$kept->id} added")->assertDontSee('Logged in from IP');

        Livewire::test(ActivityLogPage::class)->set('search', 'deleted')
            ->assertSee("Purchase #{$gone->id} deleted")->assertDontSee("Purchase #{$kept->id} added");

        Livewire::test(ActivityLogPage::class)->set('date', now()->subDays(3)->toDateString())
            ->assertSee('No activity found.');

        $this->get('/')->assertSee('Recent activity')->assertSee("Purchase #{$gone->id} deleted");
    }

    public function test_log_page_needs_login(): void
    {
        $this->get('/activity')->assertRedirect('/login');
    }
}
