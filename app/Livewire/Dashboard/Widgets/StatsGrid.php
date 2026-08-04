<?php
// app/Livewire/Dashboard/Widgets/StatsGrid.php

namespace App\Livewire\Dashboard\Widgets;

use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\StatisticsService;
use Livewire\Attributes\On;
use Livewire\Component;

class StatsGrid extends Component
{
    public string $role = '';

    public function mount(string $role): void
    {
        $this->role = $role;
    }

    #[On('refresh-widgets')]
    public function refresh(): void { /* re-render déclenché automatiquement */ }

    public function with(): array
    {
        $user     = auth()->user();
        $schoolId = $user->school_id;
        $stats    = DashboardService::stats($schoolId);

        $kpis = match ($this->role) {
            'admin'       => $this->adminKpis($stats),
            'comptable'   => $this->comptableKpis($stats),
            'enseignant'  => $this->teacherKpis($stats, $user),
            'surveillant' => $this->surveillantKpis($stats),
            'parent'      => $this->parentKpis($stats, $user),
            default       => [],
        };

        return compact('kpis');
    }

    // ── Constructeurs de KPIs par rôle ──────────────────────────

    private function adminKpis(StatisticsService $stats): array
    {
        $d = $stats->adminKpis();
        return [
            [
                'label' => 'Élèves inscrits',
                'value' => number_format($d['totalStudents']),
                'delta' => ($d['studentDelta'] >= 0 ? '+' : '').$d['studentDelta'].'%',
                'up'    => $d['studentDelta'] >= 0,
                'sub'   => 'vs mois précédent',
                'icon'  => 'users',
                'color' => 'blue',
            ],
            [
                'label' => 'Revenus encaissés',
                'value' => number_format($d['totalPaid'], 0, ',', ' ').' DJF',
                'delta' => $d['recoveryRate'].'%',
                'up'    => true,
                'sub'   => 'taux de recouvrement',
                'icon'  => 'currency',
                'color' => 'green',
            ],
            [
                'label' => 'Factures en retard',
                'value' => number_format($d['overdueCount']),
                'delta' => number_format($d['overdueAmount'], 0, ',', ' ').' DJF',
                'up'    => false,
                'sub'   => 'montant total dû',
                'icon'  => 'alert',
                'color' => 'red',
            ],
            [
                'label' => 'Taux de présence',
                'value' => $d['presenceRate'].'%',
                'delta' => '7 derniers jours',
                'up'    => $d['presenceRate'] >= 80,
                'sub'   => $d['presenceRate'] >= 80 ? 'Satisfaisant' : 'Attention',
                'icon'  => 'calendar',
                'color' => $d['presenceRate'] >= 80 ? 'green' : 'amber',
            ],
        ];
    }

    private function comptableKpis(StatisticsService $stats): array
    {
        $d = $stats->accountantKpis();
        return [
            [
                'label' => "Encaissé aujourd'hui",
                'value' => number_format($d['today'], 0, ',', ' ').' DJF',
                'delta' => now()->locale('fr')->isoFormat('D MMMM'),
                'up'    => true, 'sub' => '', 'icon' => 'currency', 'color' => 'green',
            ],
            [
                'label' => 'Ce mois',
                'value' => number_format($d['month'], 0, ',', ' ').' DJF',
                'delta' => now()->locale('fr')->isoFormat('MMMM YYYY'),
                'up'    => true, 'sub' => '', 'icon' => 'trending', 'color' => 'blue',
            ],
            [
                'label' => 'Cette année',
                'value' => number_format($d['year'], 0, ',', ' ').' DJF',
                'delta' => (string) now()->year,
                'up'    => true, 'sub' => '', 'icon' => 'currency', 'color' => 'blue',
            ],
            [
                'label' => 'Impayés échus',
                'value' => number_format($d['unpaidCount']),
                'delta' => number_format($d['overdueAmount'], 0, ',', ' ').' DJF',
                'up'    => false, 'sub' => 'à recouvrir', 'icon' => 'alert', 'color' => 'red',
            ],
        ];
    }

    private function teacherKpis(StatisticsService $stats, $user): array
    {
        $staff = $user->staff;
        if (! $staff) return [];
        $d = $stats->teacherKpis($staff->id);
        return [
            [
                'label' => 'Mes classes',
                'value' => count($d['classIds']),
                'delta' => '', 'up' => true, 'sub' => '',
                'icon'  => 'class', 'color' => 'blue',
            ],
            [
                'label' => 'Élèves total',
                'value' => $d['totalStudents'],
                'delta' => '', 'up' => true, 'sub' => '',
                'icon'  => 'users', 'color' => 'blue',
            ],
            [
                'label' => 'Présents aujourd\'hui',
                'value' => $d['presentToday'],
                'delta' => 'Absents : '.$d['absentToday'],
                'up'    => true, 'sub' => '',
                'icon'  => 'check', 'color' => 'green',
            ],
            [
                'label' => 'Rendus à corriger',
                'value' => $d['pendingHomeworks'],
                'delta' => '', 'up' => false, 'sub' => '',
                'icon'  => 'homework', 'color' => 'amber',
            ],
        ];
    }

    private function surveillantKpis(StatisticsService $stats): array
    {
        $d = $stats->surveillantKpis();
        return [
            [
                'label' => 'Présents',
                'value' => $d['present'],
                'delta' => $d['rate'].'%', 'up' => true,
                'sub'   => 'taux présence', 'icon' => 'check', 'color' => 'green',
            ],
            [
                'label' => 'Absents',
                'value' => $d['absent'],
                'delta' => '', 'up' => false,
                'sub'   => "aujourd'hui", 'icon' => 'alert', 'color' => 'red',
            ],
            [
                'label' => 'Retards',
                'value' => $d['late'],
                'delta' => '', 'up' => false,
                'sub'   => "aujourd'hui", 'icon' => 'clock', 'color' => 'amber',
            ],
            [
                'label' => 'Injustifiées',
                'value' => $d['unjustified'],
                'delta' => '', 'up' => false,
                'sub'   => 'cette semaine', 'icon' => 'alert', 'color' => 'red',
            ],
        ];
    }

    private function parentKpis(StatisticsService $stats, $user): array
    {
        $children = $stats->parentKpis($user->id);
        if (empty($children)) return [];
        $child = $children[0];
        return [
            [
                'label' => $child['name'],
                'value' => $child['avg'] !== null ? number_format($child['avg'], 2).'/20' : '—',
                'delta' => $child['period'] ?? '',
                'up'    => ($child['avg'] ?? 0) >= 10,
                'sub'   => 'Dernière moyenne', 'icon' => 'star', 'color' => 'blue',
            ],
            [
                'label' => 'Absences',
                'value' => $child['absences'],
                'delta' => '', 'up' => false,
                'sub'   => 'cette année', 'icon' => 'calendar', 'color' => 'red',
            ],
            [
                'label' => 'Devoirs en attente',
                'value' => $child['pending_hw'],
                'delta' => '', 'up' => false,
                'sub'   => 'à rendre', 'icon' => 'homework', 'color' => 'amber',
            ],
            [
                'label' => 'Solde restant',
                'value' => number_format($child['balance'], 0, ',', ' ').' DJF',
                'delta' => '',
                'up'    => $child['balance'] <= 0,
                'sub'   => $child['balance'] <= 0 ? 'À jour ✓' : 'À régler',
                'icon'  => 'currency',
                'color' => $child['balance'] <= 0 ? 'green' : 'red',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.widgets.stats-grid');
    }
}
