<?php
// app/Livewire/Dashboard/Widgets/RevenueChart.php

namespace App\Livewire\Dashboard\Widgets;

use App\Services\Dashboard\ChartService;
use App\Services\Dashboard\DashboardService;
use Livewire\Attributes\On;
use Livewire\Component;

class RevenueChart extends Component
{
    public string $chartJson = '{}';

    public function mount(): void { $this->loadData(); }

    #[On('refresh-widgets')]
    public function refresh(): void { $this->loadData(); }

    private function loadData(): void
    {
        $stats       = DashboardService::stats(auth()->user()->school_id);
        $raw         = $stats->revenueByMonth();
        $chart       = ChartService::monthlyArea($raw);
        $this->chartJson = json_encode([
            'series'     => [['name' => 'Encaissements', 'data' => $chart['series']]],
            'categories' => $chart['categories'],
            'total'      => array_sum($chart['series']),
        ]);
    }

    public function render() { return view('livewire.dashboard.widgets.revenue-chart'); }
}
