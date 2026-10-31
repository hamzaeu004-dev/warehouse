@php
    $s = $summary;
    $n = fn ($v, $d = 0) => number_format((float) $v, $d);
    $rs = fn ($v) => 'Rs '.number_format((float) $v);
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    $days = (int) round($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay())) + 1;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Warehouse report {{ $from->format('d M Y') }} - {{ $to->format('d M Y') }}</title>
    <style>
        @page { margin: 28px 30px 40px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10.5px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0; color: #115e59; }
        h2 { font-size: 13px; margin: 18px 0 6px; padding-bottom: 4px; border-bottom: 2px solid #0f766e; color: #0f766e; }
        .muted { color: #64748b; }
        .head td { vertical-align: top; }
        table { width: 100%; border-collapse: collapse; }
        .boxes td { width: 25%; padding: 8px 10px; border: 1px solid #cbd5e1; background: #f8fafc; }
        .boxes .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: .4px; }
        .boxes .value { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .boxes .hint { font-size: 9px; color: #64748b; margin-top: 2px; }
        .grid th { background: #0f766e; color: #fff; font-size: 9.5px; text-align: left; padding: 5px 6px; }
        .grid td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        .grid tr:nth-child(even) td { background: #f8fafc; }
        .grid .r { text-align: right; }
        .grid tfoot td { font-weight: bold; background: #ccfbf1; border-top: 1.5px solid #0f766e; }
        .red { color: #b91c1c; }
        .empty { padding: 8px; color: #64748b; font-style: italic; }
        .note { margin-top: 6px; font-size: 9.5px; color: #475569; }
        footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 8.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <footer>Warehouse Inventory · generated {{ $generatedAt->format('d M Y, g:i a') }} · amounts in Pakistani Rupees</footer>

    <table class="head">
        <tr>
            <td>
                <h1>Warehouse Report</h1>
                <div class="muted">Purchases, deliveries to branches and wastage</div>
            </td>
            <td style="text-align: right;">
                <div style="font-size: 12px; font-weight: bold;">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div>
                <div class="muted">{{ $days }} {{ Str::plural('day', $days) }}</div>
            </td>
        </tr>
    </table>

    <table class="boxes" style="margin-top: 14px;">
        <tr>
            <td>
                <div class="label">Purchased</div>
                <div class="value">{{ $rs($s['purchased']) }}</div>
                <div class="hint">{{ $s['purchase_count'] }} {{ Str::plural('purchase', $s['purchase_count']) }}</div>
            </td>
            <td>
                <div class="label">Delivered to branches</div>
                <div class="value">{{ $rs($s['delivered']) }}</div>
                <div class="hint">{{ $s['invoice_count'] }} {{ Str::plural('invoice', $s['invoice_count']) }} · cost {{ $rs($s['delivered_cost']) }}</div>
            </td>
            <td>
                <div class="label">Wastage</div>
                <div class="value red">{{ $rs($s['wastage']) }}</div>
                <div class="hint">Wasted, expired, damaged</div>
            </td>
            <td>
                <div class="label">Stock in warehouse now</div>
                <div class="value">{{ $rs($s['stock_value_now']) }}</div>
                <div class="hint">At purchase cost</div>
            </td>
        </tr>
    </table>

    {{-- 1. Purchases --}}
    <h2>1. Purchasing</h2>
    @if ($purchasedItems->isEmpty())
        <div class="empty">No purchases in this period.</div>
    @else
        <table class="grid">
            <thead><tr><th>Item</th><th class="r">Quantity</th><th class="r">Avg rate</th><th class="r">Amount</th></tr></thead>
            <tbody>
                @foreach ($purchasedItems as $row)
                    <tr>
                        <td>{{ $row->name }}</td>
                        <td class="r">{{ $q($row->qty) }} {{ $row->unit }}</td>
                        <td class="r">{{ $n($row->qty > 0 ? $row->amount / $row->qty : 0, 2) }}</td>
                        <td class="r">{{ $rs($row->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="3">Total purchased</td><td class="r">{{ $rs($s['purchased']) }}</td></tr></tfoot>
        </table>

        <table class="grid" style="margin-top: 8px;">
            <thead><tr><th>Date</th><th>Purchase</th><th>Store</th><th class="r">Items</th><th class="r">Amount</th></tr></thead>
            <tbody>
                @foreach ($purchases as $p)
                    <tr>
                        <td>{{ $p->purchase_date->format('D, d M Y') }}</td>
                        <td>#{{ $p->id }}</td>
                        <td>{{ $p->store_name ?: '–' }}</td>
                        <td class="r">{{ $p->lines_count }}</td>
                        <td class="r">{{ $rs($p->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- 2. Deliveries --}}
    <h2>2. Delivered to branches</h2>
    @if ($deliveredByBranch->isEmpty())
        <div class="empty">Nothing was delivered to branches in this period.</div>
    @else
        <table class="grid">
            <thead><tr><th>Branch</th><th class="r">Invoices</th><th class="r">Amount</th></tr></thead>
            <tbody>
                @foreach ($deliveredByBranch as $b)
                    <tr><td>{{ $b->name }}</td><td class="r">{{ $b->invoices }}</td><td class="r">{{ $rs($b->amount) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td>Total delivered</td><td class="r">{{ $s['invoice_count'] }}</td><td class="r">{{ $rs($s['delivered']) }}</td></tr></tfoot>
        </table>

        <table class="grid" style="margin-top: 8px;">
            <thead><tr><th>Item</th><th class="r">Quantity</th><th class="r">Invoice amount</th><th class="r">Stock cost</th></tr></thead>
            <tbody>
                @foreach ($deliveredItems as $row)
                    <tr>
                        <td>{{ $row->name }}</td>
                        <td class="r">{{ $q($row->qty) }} {{ $row->unit }}</td>
                        <td class="r">{{ $rs($row->amount) }}</td>
                        <td class="r">{{ $rs($row->cost) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="2">Total</td><td class="r">{{ $rs($s['delivered']) }}</td><td class="r">{{ $rs($s['delivered_cost']) }}</td></tr></tfoot>
        </table>
    @endif
    @if ($s['unsent_batch_count'])
        <div class="note">Also prepared in {{ $s['unsent_batch_count'] }} {{ Str::plural('batch', $s['unsent_batch_count']) }} but not sent to a branch yet: {{ $rs($s['unsent_batches']) }} (at cost).</div>
    @endif

    {{-- 3. Wastage --}}
    <h2>3. Wastage</h2>
    @if ($wastage->isEmpty())
        <div class="empty">No wastage recorded in this period.</div>
    @else
        <table class="grid">
            <thead><tr><th>Item</th><th>Reason</th><th class="r">Quantity</th><th class="r">Value</th></tr></thead>
            <tbody>
                @foreach ($wastage as $row)
                    <tr>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->reason_label }}</td>
                        <td class="r">{{ $q($row->qty) }} {{ $row->unit }}</td>
                        <td class="r red">{{ $rs($row->value) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="3">Total wastage</td><td class="r red">{{ $rs($s['wastage']) }}</td></tr></tfoot>
        </table>
    @endif
    @if ($s['count_difference'] != 0)
        <div class="note">Stock count corrections in this period: {{ $s['count_difference'] < 0 ? 'short by' : 'extra found' }} {{ $rs(abs($s['count_difference'])) }} (at cost).</div>
    @endif
</body>
</html>
