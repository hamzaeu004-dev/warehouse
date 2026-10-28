<?php

namespace App\Livewire\Transfers;

use App\Livewire\Purchases\Pickable;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Transfer;
use App\Services\StockService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * New invoice to a branch, or editing a saved one (/transfers/{transfer}/edit).
 */
class Create extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public ?int $from_batch_id = null;

    public ?Batch $fromBatch = null;

    public ?int $branch_id = null;

    public string $invoice_no = '';

    public string $transfer_date = '';

    public string $due_days = '0';

    public string $po_no = '';

    public string $notes = '';

    public function mount(?Transfer $transfer = null): void
    {
        $this->transfer_date = now()->toDateString();
        $this->invoice_no = (string) Transfer::nextInvoiceNo();
        $this->branch_id = request()->integer('branch') ?: null;

        if ($transfer?->exists) {
            $this->editingId = $transfer->id;
            $this->branch_id = $transfer->branch_id;
            $this->invoice_no = (string) $transfer->invoice_no;
            $this->transfer_date = $transfer->transfer_date->toDateString();
            $this->due_days = (string) $transfer->due_days;
            $this->po_no = (string) $transfer->po_no;
            $this->notes = (string) $transfer->notes;
        } else {
            $fromBatchId = request()->integer('from_batch') ?: null;
            if ($fromBatchId) {
                $batch = Batch::with(['lines.item.category'])->find($fromBatchId);
                if ($batch) {
                    $this->from_batch_id = $batch->id;
                    $this->fromBatch = $batch;
                    $this->po_no = $batch->batch_no;
                    $this->notes = "Dispatched from Batch {$batch->batch_no}";
                }
            }
        }
    }

    public function selectBatch(?int $batchId): void
    {
        if ($batchId) {
            $this->redirectRoute('transfers.create', ['from_batch' => $batchId], navigate: true);
        } else {
            $this->redirectRoute('transfers.create', navigate: true);
        }
    }

    private function editing(): ?Transfer
    {
        return $this->editingId ? Transfer::findOrFail($this->editingId) : null;
    }

    public function save(array $lines, StockService $stock)
    {
        $transfer = $this->editing();
        $keep = $transfer ? $transfer->lines()->pluck('item_id')->all() : [];

        $data = Validator::make(
            [
                'branch_id' => $this->branch_id, 'invoice_no' => $this->invoice_no, 'transfer_date' => $this->transfer_date,
                'due_days' => $this->due_days, 'po_no' => $this->po_no, 'notes' => $this->notes, 'lines' => $lines,
                'from_batch_id' => $this->from_batch_id,
            ],
            [
                'branch_id' => ['required', 'exists:branches,id'],
                'invoice_no' => ['required', 'integer', 'min:1', 'max:4000000000', Rule::unique('transfers', 'invoice_no')->ignore($this->editingId)],
                'transfer_date' => ['required', 'date', 'before_or_equal:today'],
                'due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'po_no' => ['nullable', 'string', 'max:50'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'from_batch_id' => ['nullable', 'integer', 'exists:batches,id'],
                'lines' => ['required', 'array', 'min:1', 'max:200'],
                'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $keep))],
                'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:100000'],
                'lines.*.pieces' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:1000000'],
                'lines.*.description' => ['nullable', 'string', 'max:150'],
            ],
            [
                'branch_id.required' => 'Please select the branch that placed the order.',
                'invoice_no.unique' => 'This invoice number is already used.',
                'lines.required' => 'Add at least one item.',
                'lines.*.item_id.exists' => 'An item in the list is hidden or was removed. Take it out of the list.',
                'lines.min' => 'Add at least one item.',
                'lines.*.qty.required' => 'Enter Kgs / quantity for every item.',
                'lines.*.qty.gt' => 'Kgs / quantity must be greater than 0.',
                'lines.*.qty.max' => 'Quantity is too large. Check the number.',
                'lines.*.rate.required' => 'Enter a rate for every item.',
                'lines.*.rate.max' => 'Rate is too large. Check the number.',
                'transfer_date.before_or_equal' => 'Date cannot be in the future.',
            ]
        )->validate();

        if ($transfer) {
            $transfer = $stock->updateTransfer($transfer, $data, $data['lines']);
            session()->flash('success', "Transfer #{$transfer->invoice_no} updated. Stock corrected.");
        } else {
            $transfer = $stock->recordTransfer($data, $data['lines']);
            session()->flash('success', "Stock sent to {$transfer->branch->name}. Invoice #{$transfer->invoice_no} created.");
        }

        $this->redirectRoute('transfers.show', $transfer, navigate: true);

        // Tells the form the save worked, so it drops its local draft
        return true;
    }

    public function render()
    {
        $transfer = $this->editing();
        $lines = $transfer?->lines()->get() ?? collect();

        $initialRows = null;
        if ($transfer) {
            $initialRows = $lines->map(fn ($l) => [
                'item_id' => $l->item_id, 'qty' => (float) $l->qty, 'pieces' => $l->pieces !== null ? (float) $l->pieces : '',
                'price' => (float) $l->unit_cost, 'description' => (string) $l->description,
            ])->values()->all();
        } elseif ($this->fromBatch) {
            $initialRows = $this->fromBatch->lines->map(fn ($l) => [
                'item_id' => $l->item_id,
                'qty' => (float) $l->qty,
                'pieces' => '',
                'price' => (float) ($l->item->sale_rate ?: ($l->unit_cost ?: $l->item->avg_cost)),
                'description' => 'From Batch ' . $this->fromBatch->batch_no,
            ])->values()->all();
        }

        return view('livewire.transfers.create', [
            'items' => Pickable::all(
                $transfer
                    ? $lines->pluck('item_id')->all()
                    : ($this->fromBatch ? $this->fromBatch->lines->pluck('item_id')->all() : [])
            ),
            'branches' => Branch::orderBy('id')->get(),
            'initialRows' => $initialRows,
            'isEditing' => (bool) $transfer,
            'fromBatch' => $this->fromBatch,
            'availableBatches' => Batch::whereDoesntHave('transfer')->latest('batch_date')->latest('id')->take(10)->get(),
        ])->title($transfer ? "Edit Transfer #{$transfer->invoice_no}" : 'Send Stock to Branch');
    }
}
