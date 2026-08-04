<?php
// app/Livewire/Dashboard/DashboardPage.php

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class DashboardPage extends Component
{
    public string $role    = '';
    public array  $widgets = [];

    public function mount(): void
    {
        $user          = auth()->user();
        $this->role    = DashboardService::role($user);
        $this->widgets = DashboardService::widgets($this->role);
    }

    #[On('academic-year-changed')]
    public function onYearChanged(): void
    {
        $this->dispatch('refresh-widgets');
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard-page');
    }
}
