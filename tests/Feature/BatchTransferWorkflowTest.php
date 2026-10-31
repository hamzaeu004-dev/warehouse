<?php

namespace Tests\Feature;

use App\Livewire\Transfers\Create as TransferCreate;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BatchTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, SampleDataSeeder::class]);
    }

    public function test_batch_to_branch_transfer_flow_without_double_deduction(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $cat = Category::first();
        $branch = Branch::first();
        $stockService = app(StockService::class);

        // 1. Warehouse has 100 kg chicken
        $item = Item::create([
            'category_id' => $cat->id,
            'name' => 'Marinated Chicken Special',
            'unit' => 'kg',
            'stock_qty' => 100.0,
            'avg_cost' => 500.0,
            'is_active' => true,
        ]);

        // 2. Manager receives call from branch & prepares Batch for 40 kg
        $batch = $stockService->recordBatch(
            [
                'batch_date' => now()->toDateString(),
                'batch_no' => 'B-0099',
                'notes' => 'Prepared on call from branch',
            ],
            [
                ['item_id' => $item->id, 'qty' => 40.0, 'unit' => 'kg'],
            ]
        );

        $item->refresh();
        $this->assertEquals(60.0, (float) $item->stock_qty, 'Warehouse stock should be 60 kg after batch creation');

        // 3. Manager goes to Send (transfers.create) with ?from_batch=$batch->id
        Livewire::withQueryParams(['from_batch' => $batch->id])
            ->test(TransferCreate::class)
            ->assertSet('from_batch_id', $batch->id)
            ->set('branch_id', $branch->id)
            ->set('invoice_no', '90001')
            ->set('transfer_date', now()->toDateString())
            ->call('save', [
                [
                    'item_id' => $item->id,
                    'qty' => 40.0,
                    'pieces' => '',
                    'rate' => 650.0,
                    'description' => 'Dispatched batch',
                ],
            ])
            ->assertHasNoErrors();

        // 4. Verify warehouse stock was NOT double deducted! (Should remain 60 kg, not 20 kg)
        $item->refresh();
        $this->assertEquals(60.0, (float) $item->stock_qty, 'Stock should NOT be double deducted when transferring batch');

        // 5. Verify batch and transfer are linked
        $batch->refresh();
        $this->assertNotNull($batch->transfer, 'Batch should be linked to the transfer');
        $this->assertEquals('90001', $batch->transfer->invoice_no);
        $this->assertEquals(26000.0, (float) $batch->transfer->total_value); // 40 * 650

        // 6. Verify deleting the batch while transferred is blocked
        $this->expectException(ValidationException::class);
        $stockService->deleteBatch($batch);
    }
}
