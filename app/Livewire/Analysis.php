<?php

namespace App\Livewire;

use App\Services\ProfitReport;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Analysis')]
class Analysis extends Component
{
    // How many periods the trend charts show
    private const SERIES_LENGTH = ['day' => 14, 'week' => 8, 'month' => 12, 'year' => 5];

    #[Url(except: 'week')]
    public string $period = 'week';

    // Any date inside the period being looked at
    #[Url(except: '')]
    public string $date = '';

    // 'cash': profit on money the branches paid (default); 'invoice': on invoice amounts
    #[Url(except: 'cash')]
    public string $basis = 'cash';

    public function setBasis(string $basis): void
    {
        $this->basis = $basis === 'invoice' ? 'invoice' : 'cash';
    }

    public function setPeriod(string $period): void
    {
        if (in_array($period, ProfitReport::PERIODS, true)) {
            $this->period = $period;
        }
    }

    public function previous(): void
    {
        $this->date = ProfitReport::shift($this->period, $this->anchor(), -1)->toDateString();
    }

    public function next(): void
    {
        if (! $this->isCurrent()) {
            $this->date = ProfitReport::shift($this->period, $this->anchor(), 1)->toDateString();
        }
    }

    public function today(): void
    {
        $this->date = '';
    }

    private function anchor(): Carbon
    {
        try {
            $date = $this->date !== '' ? Carbon::parse($this->date) : now();
        } catch (\Throwable) {
            $date = now();
        }

        return $date->greaterThan(now()) ? now() : $date;
    }

    private function isCurrent(): bool
    {
        [, $to] = ProfitReport::range($this->period, $this->anchor());

        return $to->greaterThanOrEqualTo(now()->startOfDay());
    }

    public function render()
    {
        if (! in_array($this->period, ProfitReport::PERIODS, true)) {
            $this->period = 'week';
        }

        $anchor = $this->anchor();
        [$from, $to] = ProfitReport::range($this->period, $anchor);
        $previous = ProfitReport::range($this->period, ProfitReport::shift($this->period, $anchor, -1));

        return view('livewire.analysis', [
            'from' => $from,
            'to' => $to,
            'label' => ProfitReport::label($this->period, $from, $to),
            'isCurrent' => $this->isCurrent(),
            'totals' => ProfitReport::totals($from, $to),
            'previousTotals' => ProfitReport::totals(...$previous),
            'series' => ProfitReport::series($this->period, $anchor, self::SERIES_LENGTH[$this->period]),
            'branches' => ProfitReport::byBranch($from, $to),
            'items' => ProfitReport::byItem($from, $to),
            'weekStart' => ucfirst(config('inventory.week_starts_on')),
            'unpaidAll' => round((float) \App\Models\Transfer::sum('total_value') - (float) \App\Models\BranchPayment::sum('amount'), 2),
        ]);
    }
}
