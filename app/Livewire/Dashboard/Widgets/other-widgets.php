<?php


// app/Livewire/Dashboard/Widgets/TopDebtors.php

namespace App\Livewire\Dashboard\Widgets;

use App\Services\Dashboard\ChartService;
use App\Services\Dashboard\DashboardService;
use Livewire\Attributes\On;
use Livewire\Component;

class TopDebtors extends Component
{
    public array  $debtors   = [];
    public string $chartJson = '{}';

    public function mount(): void { $this->loadData(); }

    #[On('refresh-widgets')]
    public function refresh(): void { $this->loadData(); }

    private function loadData(): void
    {
        $stats         = DashboardService::stats(auth()->user()->school_id);
        $this->debtors = $stats->topDebtors(8);
        $this->chartJson = json_encode(ChartService::horizontalBar($this->debtors));
    }

    public function render() { return view('livewire.dashboard.widgets.top-debtors'); }
}
