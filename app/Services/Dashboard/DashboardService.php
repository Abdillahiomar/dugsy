<?php
// app/Services/Dashboard/DashboardService.php

namespace App\Services\Dashboard;

use Illuminate\Contracts\Auth\Authenticatable;
use App\Services\Dashboard\StatisticsService;

class DashboardService
{
    /** Retourne le rôle normalisé de l'utilisateur */
    public static function role(Authenticatable $user): string
    {
        $role = $user->roles->first()?->name ?? 'guest';
        return match ($role) {
            'directeur' => 'admin', // même dashboard
            default     => $role,
        };
    }

    /** Widgets actifs pour un rôle */
    public static function widgets(string $role): array
    {
        return match ($role) {
            'admin' => [
                'stats', 'revenue_chart', 'enrollment_chart',
                'attendance_chart', 'payment_methods',
                'recent_payments', 'top_debtors',
            ],
            'comptable' => [
                'stats', 'revenue_chart', 'payment_methods',
                'recent_payments', 'top_debtors',
            ],
            'enseignant' => [
                'stats', 'attendance_chart',
            ],
            'surveillant' => [
                'stats', 'attendance_chart',
            ],
            'parent' => ['stats'],
            default  => ['stats'],
        };
    }

    /** Instancie un StatisticsService */
    public static function stats(int $schoolId): StatisticsService
    {
        return new StatisticsService($schoolId);
    }
}
