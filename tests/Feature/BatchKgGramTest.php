<?php

namespace Tests\Feature;

use App\Livewire\Batches\Create as BatchCreate;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BatchKgGramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_kg_item_used_in_grams_converts_correctly(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $cat = Category::first();
        $chilli = Item::create([
            'category_id' => $cat->id,
            'name' => 'Red Chilli Test',
            'unit' => 'kg',
            'stock_qty' => 5.0,
            'avg_cost' => 1000.0,
            'is_active' => true,
        ]);

        $stock = app(StockService::class);

        // Record a batch using 500 grams
        $batch = $stock->recordBatch(
            [
                'batch_date' => now()->toDateString(),
                'batch_no' => 'B-TEST-01',
                'notes' => 'Testing 500 grams usage',
            ],
            [
                ['item_id' => $chilli->id, 'qty' => 500, 'unit' => 'g'],
            ]
        );

        $chilli->refresh();
        // 5.0 kg - (500 / 1000) kg = 4.5 kg
        $this->assertEquals(4.5, (float) $chilli->stock_qty);

        // Total cost: 0.5 kg * Rs 1000 = Rs 500
        $this->assertEquals(500.0, (float) $batch->total_cost);

        $line = $batch->lines->first();
        $this->assertEquals(0.5, (float) $line->qty);
        $this->assertEquals('g', $line->unit);
        $this->assertEquals(500, (float) $line->entered_qty);
        $this->assertEquals(500, $line->displayQty());
        $this->assertEquals('g', $line->displayUnit());

        // View the batch show page
        $response = $this->get(route('batches.show', $batch));
        $response->assertOk();
        $response->assertSee('500');
        $response->assertSee('g');
        $response->assertSee('(0.5 kg)');
    }

    public function test_kg_item_used_in_kg_deducts_correctly(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $cat = Category::first();
        $chicken = Item::create([
            'category_id' => $cat->id,
            'name' => 'Chicken Test',
            'unit' => 'kg',
            'stock_qty' => 10.0,
            'avg_cost' => 600.0,
            'is_active' => true,
        ]);

        $stock = app(StockService::class);

        // Record a batch using 2 kg
        $batch = $stock->recordBatch(
            [
                'batch_date' => now()->toDateString(),
                'batch_no' => 'B-TEST-02',
            ],
            [
                ['item_id' => $chicken->id, 'qty' => 2, 'unit' => 'kg'],
            ]
        );

        $chicken->refresh();
        $this->assertEquals(8.0, (float) $chicken->stock_qty);
        $this->assertEquals(1200.0, (float) $batch->total_cost);

        $line = $batch->lines->first();
        $this->assertEquals(2.0, (float) $line->qty);
        $this->assertEquals('kg', $line->displayUnit());
    }

    public function test_gram_item_used_directly_in_grams(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $cat = Category::first();
        $saffron = Item::create([
            'category_id' => $cat->id,
            'name' => 'Saffron Test',
            'unit' => 'g',
            'stock_qty' => 100.0,
            'avg_cost' => 50.0,
            'is_active' => true,
        ]);

        $stock = app(StockService::class);

        $batch = $stock->recordBatch(
            [
                'batch_date' => now()->toDateString(),
            ],
            [
                ['item_id' => $saffron->id, 'qty' => 20, 'unit' => 'g'],
            ]
        );

        $saffron->refresh();
        $this->assertEquals(80.0, (float) $saffron->stock_qty);
        $this->assertEquals(1000.0, (float) $batch->total_cost);
    }

    public function test_overstock_in_grams_is_prevented(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $cat = Category::first();
        $item = Item::create([
            'category_id' => $cat->id,
            'name' => 'Spice Overstock Test',
            'unit' => 'kg',
            'stock_qty' => 0.2, // 200 grams
            'avg_cost' => 2000.0,
            'is_active' => true,
        ]);

        $stock = app(StockService::class);

        // Trying to use 500 grams when only 200g (0.2kg) in stock
        $this->expectException(ValidationException::class);

        $stock->recordBatch(
            [
                'batch_date' => now()->toDateString(),
            ],
            [
                ['item_id' => $item->id, 'qty' => 500, 'unit' => 'g'],
            ]
        );
    }

    public function test_deleting_batch_restores_converted_stock(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $cat = Category::first();
        $item = Item::create([
            'category_id' => $cat->id,
            'name' => 'Restore Stock Test',
            'unit' => 'kg',
            'stock_qty' => 5.0,
            'avg_cost' => 1000.0,
            'is_active' => true,
        ]);

        $stock = app(StockService::class);

        $batch = $stock->recordBatch(
            [
                'batch_date' => now()->toDateString(),
            ],
            [
                ['item_id' => $item->id, 'qty' => 500, 'unit' => 'g'],
            ]
        );

        $item->refresh();
        $this->assertEquals(4.5, (float) $item->stock_qty);

        // Delete the batch
        $stock->deleteBatch($batch);

        $item->refresh();
        $this->assertEquals(5.0, (float) $item->stock_qty);
    }
}
