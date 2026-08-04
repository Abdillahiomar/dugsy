<?php

namespace App\Livewire\Dashboard\Widgets;

use App\Services\Dashboard\ChartService;
use App\Services\Dashboard\DashboardService;
use Livewire\Attributes\On;
use Livewire\Component;

class PaymentChart extends Component
{
    public string $chartJson = '{}';

    public function mount(): void { $this->loadData(); }

    #[On('refresh-widgets')]
    public function refresh(): void { $this->loadData(); }

    private function loadData(): void
    {
        $stats       = DashboardService::stats(auth()->user()->school_id);
        $kpis        = $stats->accountantKpis();
        $this->chartJson = json_encode(ChartService::paymentMethods($kpis['byMethod']));
    }

    public function render() { return view('livewire.dashboard.widgets.payment-chart'); }
}
