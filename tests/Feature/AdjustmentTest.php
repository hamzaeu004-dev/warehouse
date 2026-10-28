<?php

namespace Tests\Feature;

use App\Livewire\Adjustments\Create;
use App\Livewire\Adjustments\Show;
use App\Livewire\Items\Show as ItemShow;
use App\Models\Adjustment;
use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private Item $oil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::first());

        $this->oil = Item::where('name', 'Frying oil')->first();
        app(StockService::class)->recordPurchase(['purchase_date' => now()->toDateString()], [
            ['item_id' => $this->oil->id, 'qty' => 10, 'unit_price' => 500],
        ]);
    }

    public function test_pages_load(): void
    {
        $this->get('/adjustments')->assertOk();
        $this->get('/adjustments/new')->assertOk();
        $this->get("/adjustments/new?item={$this->oil->id}&reason=count")->assertOk();
    }

    public function test_wastage_reduces_stock_and_records_loss(): void
    {
        Livewire::test(Create::class)
            ->set('reason', 'wastage')
            ->set('notes', 'Spilled')
            ->call('save', [['item_id' => $this->oil->id, 'qty' => '2']])
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertEquals(8, (float) $this->oil->refresh()->stock_qty);
        $this->assertEquals(500, (float) $this->oil->avg_cost);

        $adjustment = Adjustment::first();
        $this->assertSame('wastage', $adjustment->reason);
        $this->assertEquals(-1000, (float) $adjustment->total_value);
        $this->assertEquals(-2, (float) $adjustment->lines->first()->qty_change);

        $this->get("/adjustments/{$adjustment->id}")->assertOk()->assertSee('Wastage')->assertSee('Spilled');
        $this->get('/')->assertSee('Stock lost this month');
        $this->get("/items/{$this->oil->id}")->assertSee('Adjusted: Wastage');
    }

    public function test_cannot_remove_more_than_stock(): void
    {
        Livewire::test(Create::class)
            ->set('reason', 'expired')
            ->call('save', [['item_id' => $this->oil->id, 'qty' => '11']])
            ->assertHasErrors('lines');

        $this->assertEquals(10, (float) $this->oil->refresh()->stock_qty);
        $this->assertDatabaseCount('adjustments', 0);
    }

    public function test_count_lower_than_system_removes_difference(): void
    {
        Livewire::test(Create::class)
            ->set('reason', 'count')
            ->call('save', [['item_id' => $this->oil->id, 'counted_qty' => '7.5']])
            ->assertHasNoErrors();

        $line = Adjustment::first()->lines->first();
        $this->assertEquals(7.5, (float) $this->oil->refresh()->stock_qty);
        $this->assertEquals(10, (float) $line->system_qty);
        $this->assertEquals(7.5, (float) $line->counted_qty);
        $this->assertEquals(-2.5, (float) $line->qty_change);
        $this->assertEquals(-1250, (float) $line->total);
    }

    public function test_count_higher_than_system_adds_difference(): void
    {
        Livewire::test(Create::class)
            ->set('reason', 'count')
            ->call('save', [['item_id' => $this->oil->id, 'counted_qty' => '12']])
            ->assertHasNoErrors();

        $this->assertEquals(12, (float) $this->oil->refresh()->stock_qty);
        $this->assertEquals(1000, (float) Adjustment::first()->total_value);
    }

    public function test_count_to_zero_is_allowed(): void
    {
        Livewire::test(Create::class)
            ->set('reason', 'count')
            ->call('save', [['item_id' => $this->oil->id, 'counted_qty' => '0']])
            ->assertHasNoErrors();

        $this->assertEquals(0, (float) $this->oil->refresh()->stock_qty);
    }

    public function test_count_matching_system_saves_nothing(): void
    {
        Livewire::test(Create::class)
            ->set('reason', 'count')
            ->call('save', [['item_id' => $this->oil->id, 'counted_qty' => '10']])
            ->assertHasErrors('lines');

        $this->assertDatabaseCount('adjustments', 0);
    }

    public function test_bad_input_is_rejected(): void
    {
        $cases = [
            'unknown reason' => ['stolen', [['item_id' => $this->oil->id, 'qty' => '1']], 'reason'],
            'negative qty' => ['wastage', [['item_id' => $this->oil->id, 'qty' => '-1']], 'lines.0.qty'],
            'missing count' => ['count', [['item_id' => $this->oil->id, 'counted_qty' => '']], 'lines.0.counted_qty'],
            'negative count' => ['count', [['item_id' => $this->oil->id, 'counted_qty' => '-1']], 'lines.0.counted_qty'],
            'same item twice' => ['wastage', [['item_id' => $this->oil->id, 'qty' => '1'], ['item_id' => $this->oil->id, 'qty' => '1']], 'lines.0.item_id'],
            'other without notes' => ['other', [['item_id' => $this->oil->id, 'qty' => '1']], 'notes'],
        ];

        foreach ($cases as $name => [$reason, $lines, $errorKey]) {
            Livewire::test(Create::class)->set('reason', $reason)->call('save', $lines)->assertHasErrors($errorKey);
        }

        $this->assertEquals(10, (float) $this->oil->refresh()->stock_qty);
        $this->assertDatabaseCount('adjustments', 0);
    }

    public function test_delete_restores_stock(): void
    {
        $adjustment = app(StockService::class)->recordAdjustment(
            ['adjustment_date' => now()->toDateString(), 'reason' => 'damaged'],
            [['item_id' => $this->oil->id, 'qty' => 3]]
        );
        $this->assertEquals(7, (float) $this->oil->refresh()->stock_qty);

        Livewire::test(Show::class, ['adjustment' => $adjustment])->call('delete')->assertRedirect();

        $this->assertEquals(10, (float) $this->oil->refresh()->stock_qty);
        $this->assertDatabaseCount('adjustment_items', 0);
    }

    public function test_cannot_delete_found_stock_that_was_already_used(): void
    {
        $stock = app(StockService::class);
        $adjustment = $stock->recordAdjustment(
            ['adjustment_date' => now()->toDateString(), 'reason' => 'count'],
            [['item_id' => $this->oil->id, 'counted_qty' => 15]]
        );
        // All 15 used, so the 5 "found" can no longer be taken back
        $stock->recordAdjustment(['adjustment_date' => now()->toDateString(), 'reason' => 'wastage'], [['item_id' => $this->oil->id, 'qty' => 15]]);

        Livewire::test(Show::class, ['adjustment' => $adjustment])->call('delete')->assertHasErrors('adjustment');
        $this->assertEquals(0, (float) $this->oil->refresh()->stock_qty);
    }

    public function test_item_with_only_adjustments_cannot_be_deleted(): void
    {
        $salt = Item::where('name', 'Salt')->first();
        app(StockService::class)->recordAdjustment(
            ['adjustment_date' => now()->toDateString(), 'reason' => 'count'],
            [['item_id' => $salt->id, 'counted_qty' => 2]]
        );

        Livewire::test(ItemShow::class, ['item' => $salt])->call('delete')->assertHasErrors('delete');
        $this->assertModelExists($salt);
    }
}
