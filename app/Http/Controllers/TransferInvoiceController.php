<?php

namespace App\Http\Controllers;

use App\Models\Transfer;

class TransferInvoiceController extends Controller
{
    // Printable Commercial Invoice for a transfer (print or save as PDF from the browser)
    public function __invoke(Transfer $transfer)
    {
        $transfer->load(['branch', 'lines.item']);

        return view('invoices.transfer', [
            'transfer' => $transfer,
            'lines' => $transfer->lines,
            'totalKgs' => $transfer->lines->sum(fn ($l) => $l->invoiceKgs() ?? 0),
            'totalQty' => $transfer->lines->sum(fn ($l) => $l->invoiceQty() ?? 0),
        ]);
    }
}
