<?php

namespace App\Livewire\Transfers;

use App\Models\Transfer;
use App\Services\StockService;
use Livewire\Component;

class Show extends Component
{
    public Transfer $transfer;

    public function mount(Transfer $transfer): void
    {
        $this->transfer = $transfer->load(['branch', 'batch', 'lines.item']);
    }

    public function getWhatsappUrlProperty(): string
    {
        $branch = $this->transfer->branch;
        $date = $this->transfer->transfer_date->format('d M Y');
        $lines = $this->transfer->lines;

        $text = "📦 *ROOSTERS CENTRAL WAREHOUSE*\n";
        $text .= "📄 *Delivery Challan / Invoice #{$this->transfer->invoice_no}*\n";
        if ($this->transfer->po_no) {
            $text .= "🏷️ *Batch:* {$this->transfer->po_no}\n";
        }
        $text .= "🏢 *Branch:* {$branch->name}\n";
        $text .= "📅 *Date:* {$date}\n";
        $text .= "------------------------------------\n";
        $text .= "*ITEMS DELIVERED:*\n";

        foreach ($lines as $i => $line) {
            $qty = qty($line->qty) . ' ' . $line->item->unit;
            if ($line->pieces) {
                $qty .= " (" . qty($line->pieces) . " pcs)";
            }
            $rate = number_format($line->unit_cost);
            $amount = number_format($line->total);
            $text .= ($i + 1) . ". {$line->item->name}\n   {$qty} × Rs.{$rate} = *Rs.{$amount}*\n";
        }

        $text .= "------------------------------------\n";
        $text .= "💰 *TOTAL AMOUNT: Rs. " . number_format($this->transfer->total_value) . "*\n";
        $text .= "------------------------------------\n";
        $text .= "_Internal stock delivery record._";

        $phone = preg_replace('/[^0-9]/', '', (string) $branch->phone);
        if ($phone) {
            if (str_starts_with($phone, '03')) {
                $phone = '92' . substr($phone, 1);
            }
            return "https://api.whatsapp.com/send?phone={$phone}&text=" . urlencode($text);
        }

        return "https://api.whatsapp.com/send?text=" . urlencode($text);
    }

    public function getShareSummaryProperty(): string
    {
        $branch = $this->transfer->branch;
        $date = $this->transfer->transfer_date->format('d M Y');
        $lines = $this->transfer->lines;

        $text = "ROOSTERS CENTRAL WAREHOUSE\n";
        $text .= "Delivery Challan / Invoice #{$this->transfer->invoice_no}\n";
        if ($this->transfer->po_no) {
            $text .= "Batch: {$this->transfer->po_no}\n";
        }
        $text .= "Branch: {$branch->name}\n";
        $text .= "Date: {$date}\n";
        $text .= "------------------------------------\n";
        $text .= "ITEMS DELIVERED:\n";

        foreach ($lines as $i => $line) {
            $qty = qty($line->qty) . ' ' . $line->item->unit;
            if ($line->pieces) {
                $qty .= " (" . qty($line->pieces) . " pcs)";
            }
            $rate = number_format($line->unit_cost);
            $amount = number_format($line->total);
            $text .= ($i + 1) . ". {$line->item->name} - {$qty} @ Rs.{$rate} = Rs.{$amount}\n";
        }

        $text .= "------------------------------------\n";
        $text .= "TOTAL AMOUNT: Rs. " . number_format($this->transfer->total_value) . "\n";
        $text .= "------------------------------------";

        return $text;
    }

    public function delete(StockService $stock)
    {
        $stock->deleteTransfer($this->transfer);
        session()->flash('success', 'Transfer deleted. Stock restored.');

        return $this->redirectRoute('transfers.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.transfers.show', [
            'lines' => $this->transfer->lines,
            'whatsappUrl' => $this->whatsappUrl,
            'shareSummary' => $this->shareSummary,
        ])->title('Invoice #'.$this->transfer->invoice_no.' - '.$this->transfer->branch->name);
    }
}
