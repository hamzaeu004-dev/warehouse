<?php

namespace App\Livewire;

use App\Services\OwnerReport;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Reports')]
class Reports extends Component
{
    #[Url(except: 'this_month')]
    public string $period = 'this_month';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, OwnerReport::PERIODS) ? $period : 'this_month';
        if ($this->period === 'custom' && $this->from === '') {
            $this->from = now()->startOfMonth()->toDateString();
            $this->to = now()->toDateString();
        }
    }

    public function render()
    {
        [$from, $to] = OwnerReport::range($this->period, $this->from, $this->to);
        $query = ['period' => $this->period] + ($this->period === 'custom' ? ['from' => $from->toDateString(), 'to' => $to->toDateString()] : []);

        return view('livewire.reports', [
            'periods' => OwnerReport::PERIODS,
            'report' => OwnerReport::build($from, $to),
            'pdfUrl' => route('reports.pdf', $query),
            'viewUrl' => route('reports.pdf', $query + ['view' => 1]),
            'fileName' => OwnerReport::fileName($from, $to),
        ]);
    }
}
