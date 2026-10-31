<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\OwnerReport;
use Illuminate\Http\Request;

class ReportPdfController extends Controller
{
    // /reports/pdf?period=last_month  or  ?period=custom&from=2026-10-01&to=2026-10-31
    // Add &view=1 to open it in the browser instead of downloading.
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:'.implode(',', array_keys(OwnerReport::PERIODS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        [$from, $to] = OwnerReport::range($data['period'] ?? 'this_month', $data['from'] ?? null, $data['to'] ?? null);
        $pdf = OwnerReport::pdf($from, $to);
        $file = OwnerReport::fileName($from, $to);

        ActivityLogger::record('account.report', "Report downloaded: {$from->format('d M Y')} – {$to->format('d M Y')}");

        return $request->boolean('view') ? $pdf->stream($file) : $pdf->download($file);
    }
}
