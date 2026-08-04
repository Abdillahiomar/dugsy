<?php

// app/Livewire/Dashboard/Widgets/RecentPayments.php

namespace App\Livewire\Dashboard\Widgets;

use App\Models\StudentInvoice;
use Livewire\Attributes\On;
use Livewire\Component;

class RecentPayments extends Component
{
    public array $payments = [];

    public function mount(): void { $this->loadData(); }

    #[On('refresh-widgets')]
    public function refresh(): void { $this->loadData(); }

    private function loadData(): void
    {
        $schoolId = auth()->user()->school_id;
        $this->payments = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('status', 'paid')
         ->with(['studentSchoolYear.student','studentSchoolYear.schoolClass'])
         ->orderByDesc('updated_at')
         ->limit(8)->get()
         ->map(fn ($inv) => [
             'name'   => $inv->studentSchoolYear->student->fullName(),
             'class'  => $inv->studentSchoolYear->schoolClass?->name,
             'amount' => $inv->amount_paid,
             'method' => $inv->method ?? 'cash',
             'date'   => $inv->updated_at->locale('fr')->diffForHumans(),
         ])->toArray();

         
    }

    public function render() { return view('livewire.dashboard.widgets.recent-payments'); }
}
