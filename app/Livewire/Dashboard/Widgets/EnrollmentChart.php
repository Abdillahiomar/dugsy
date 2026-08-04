<?php

namespace App\Livewire\Dashboard\Widgets;

use App\Services\Dashboard\ChartService;
use App\Services\Dashboard\DashboardService;
use Livewire\Attributes\On;
use Livewire\Component;

class EnrollmentChart extends Component
{
    public string $chartJson = '{}';

    public function mount(): void { $this->loadData(); }

    #[On('refresh-widgets')]
    public function refresh(): void { $this->loadData(); }

    private function loadData(): void
    {
        $stats   = DashboardService::stats(auth()->user()->school_id);
        $raw     = $stats->enrollmentsByMonth();
        $byLevel = $stats->studentsByLevel();
        $chart   = ChartService::monthlyArea($raw);
        $donut   = ChartService::donut($byLevel);

        $this->chartJson = json_encode([
            'line'  => [
                'series'     => [['name' => 'Inscriptions', 'data' => $chart['series']]],
                'categories' => $chart['categories'],
            ],
            'donut' => $donut,
        ]);
    }

    public function render() { return view('livewire.dashboard.widgets.enrollment-chart'); }
}