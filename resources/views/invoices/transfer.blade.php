@php
    $num = fn ($v, $d = 2) => number_format((float) $v, $d);
    $rs = fn ($v) => 'Rs. '.number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transfer Slip #{{ $transfer->invoice_no }} - {{ $transfer->branch->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; color: #000; font-family: 'Book Antiqua', Palatino, 'Palatino Linotype', Georgia, serif; font-size: 13px; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; gap: 8px; justify-content: space-between; align-items: center; padding: 10px 12px; background: #0f766e; font-family: system-ui, sans-serif; }
        .toolbar a, .toolbar button { cursor: pointer; border: 0; border-radius: 10px; padding: 10px 14px; font-size: 14px; font-weight: 600; text-decoration: none; }
        .toolbar .back { background: transparent; color: #fff; }
        .toolbar .back:hover { background: #115e59; }
        .toolbar .print { background: #fff; color: #0f766e; }
        .toolbar .print:hover { background: #ccfbf1; }
        .scroller { overflow-x: auto; padding: 16px 8px; }
        .page { width: 794px; min-height: 1123px; margin: 0 auto; background: #fff; padding: 48px 34px; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
        .page-no { text-align: right; font-family: Arial, sans-serif; font-size: 12px; }
        h1 { margin: 2px 0 12px; text-align: right; font-size: 21px; }
        .box { border: 1px solid #000; margin-bottom: 12px; }
        .top { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 10px 12px; font-size: 12px; font-weight: bold; }
        .top div { display: flex; align-items: center; gap: 8px; justify-content: space-between; }
        .pill { flex: 1; max-width: 130px; border: 1px solid #000; background: #e5e5e5; padding: 3px 6px; text-align: center; }
        .section-title { background: #e5e5e5; border-bottom: 1px solid #000; text-align: center; font-weight: bold; font-size: 14px; padding: 2px; }
        .details { display: grid; grid-template-columns: 1.3fr 1fr .7fr; padding: 4px 12px; font-size: 12px; row-gap: 4px; }
        .details b { margin-right: 22px; }
        .deliver { padding: 8px 10px 14px; font-size: 12px; }
        .deliver .head { font-weight: bold; margin-bottom: 14px; }
        .deliver .name { font-weight: bold; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; }
        th { font-size: 13px; }
        td.r { text-align: right; }
        td.c, th.c { text-align: center; }
        tr.blank td { height: 46px; }
        tfoot td { border: 1px solid #000; }
        tfoot .words { border-right: 0; font-size: 11.5px; }
        tfoot .tot { font-weight: bold; }
        .footer { text-align: center; font-family: Arial, sans-serif; font-weight: bold; font-size: 11px; margin-top: 3px; }
        @media print {
            @page { size: A4; margin: 0; }
            body { background: #fff; }
            .toolbar { display: none; }
            .scroller { padding: 0; overflow: visible; }
            .page { box-shadow: none; margin: 0; zoom: 1 !important; }
        }
    </style>
    <script>
        // On a phone, shrink the A4 page to fit the screen instead of scrolling sideways
        function fitPage() {
            const page = document.querySelector('.page');
            const room = document.querySelector('.scroller').clientWidth - 16;
            page.style.zoom = Math.min(1, room / 794);
        }
        addEventListener('DOMContentLoaded', fitPage);
        addEventListener('resize', fitPage);
    </script>
</head>
<body>
    @php
        $whatsappText = "ROOSTERS CENTRAL WAREHOUSE\nDelivery Challan #{$transfer->invoice_no}\nBranch: {$transfer->branch->name}\nDate: {$transfer->transfer_date->format('d M Y')}\nTotal Amount: Rs. " . number_format((float) $transfer->total_value);
        $phone = preg_replace('/[^0-9]/', '', (string) $transfer->branch->phone);
        if ($phone && str_starts_with($phone, '03')) $phone = '92' . substr($phone, 1);
        $waUrl = $phone ? "https://api.whatsapp.com/send?phone={$phone}&text=" . urlencode($whatsappText) : "https://api.whatsapp.com/send?text=" . urlencode($whatsappText);
    @endphp
    <div class="toolbar">
        <a class="back" href="{{ route('transfers.show', $transfer) }}">‹ Back</a>
        <div style="display: flex; gap: 8px;">
            <a href="{{ $waUrl }}" target="_blank" style="background:#25D366; color:#fff; display:flex; align-items:center; gap:6px;">WhatsApp</a>
            <button class="print" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    <div class="scroller">
        <div class="page">
            <div class="page-no">Page 1 of 1</div>
            <h1>Stock Transfer / Delivery Challan</h1>

            <div class="box top">
                <div>Transfer No: <span class="pill">{{ $transfer->invoice_no }}</span></div>
                <div>Dispatch Date: <span class="pill">{{ $transfer->transfer_date->format('d M Y') }}</span></div>
                <div>Total Value: <span class="pill">Rs.{{ number_format((float) $transfer->total_value) }}</span></div>
            </div>

            <div class="box">
                <div class="section-title">Transfer Details</div>
                <div class="details">
                    <div><b>Branch:</b> <b>{{ $transfer->branch->name }}</b></div>
                    <div><b>STN #</b> <b>{{ $transfer->invoice_no }}</b></div>
                    <div>Email: {{ $transfer->branch->email }}</div>
                    <div>P/O No: {{ $transfer->po_no }}</div>
                    <div></div>
                </div>
            </div>

            <div class="box deliver">
                <div class="head">Deliver To</div>
                @if ($transfer->branch->contact_person)
                    <div><b>ATTN:&nbsp; {{ strtoupper($transfer->branch->contact_person) }}</b></div>
                @endif
                <div class="name">{{ strtoupper($transfer->branch->name) }}</div>
                @if ($transfer->branch->address)
                    <div>{{ strtoupper($transfer->branch->address) }}</div>
                @endif
                @if ($transfer->branch->phone)
                    <div>Ph: {{ $transfer->branch->phone }}</div>
                @endif
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:6%" class="c">S/N.</th>
                        <th style="width:26%">Item Name</th>
                        <th style="width:21%">Description</th>
                        <th style="width:7%" class="c">Kgs</th>
                        <th style="width:14%" class="c">Qty</th>
                        <th style="width:10%" class="c">Rate</th>
                        <th style="width:16%" class="c">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $i => $line)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ strtoupper($line->item->name) }}</td>
                            <td>{{ $line->description }}</td>
                            <td class="r">{{ $line->invoiceKgs() !== null ? $num($line->invoiceKgs()) : '' }}</td>
                            <td class="c">{{ $line->invoiceQty() !== null ? qty($line->invoiceQty()) : '' }}</td>
                            <td class="r">{{ $num($line->unit_cost) }}</td>
                            <td class="r">{{ $rs($line->total) }}</td>
                        </tr>
                    @endforeach
                    <tr class="blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="words">{{ rupees_in_words($transfer->total_value) }}.</td>
                        <td class="c">{{ $totalKgs > 0 ? $num($totalKgs) : '' }}</td>
                        <td class="c">{{ $totalQty > 0 ? qty($totalQty) : '' }}</td>
                        <td class="c tot">Total</td>
                        <td class="r tot">{{ $rs($transfer->total_value) }}</td>
                    </tr>
                </tfoot>
            </table>
            <div class="footer">== This is a system generated document and does not require any signature. ==</div>
        </div>
    </div>
</body>
</html>
