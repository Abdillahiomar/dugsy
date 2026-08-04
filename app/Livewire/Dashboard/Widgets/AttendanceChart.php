<?php
namespace App\Livewire\Dashboard\Widgets;

use App\Services\Dashboard\ChartService;
use App\Services\Dashboard\DashboardService;
use Livewire\Attributes\On;
use Livewire\Component;

class AttendanceChart extends Component
{
    public string $role      = '';
    public string $chartJson = '{}';

    public function mount(string $role): void { $this->role = $role; $this->loadData(); }

    #[On('refresh-widgets')]
    public function refresh(): void { $this->loadData(); }

    private function loadData(): void
    {
        $user     = auth()->user();
        $stats    = DashboardService::stats($user->school_id);

        if ($this->role === 'enseignant' && $user->staff) {
            $kpis = $stats->teacherKpis($user->staff->id);
            $days = $stats->teacherPresenceByWeek($kpis['classIds']);
        } else {
            $days = $stats->presenceByDay();
        }

        $this->chartJson = json_encode(ChartService::attendanceBar($days));
    }

    public function render() { return view('livewire.dashboard.widgets.attendance-chart'); }
}
