<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basique',
                'slug' => 'basique',
                'description' => 'Pour les petites écoles qui démarrent.',
                'price' => 15000,
                'billing_cycle' => 'monthly',
                'max_students' => 150,
                'max_staff' => 15,
                'features' => ['gestion_eleves', 'notes', 'presences'],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Pour les écoles en croissance, avec finances et bulletins.',
                'price' => 35000,
                'billing_cycle' => 'monthly',
                'max_students' => 600,
                'max_staff' => 60,
                'features' => ['gestion_eleves', 'notes', 'presences', 'finances', 'bulletins', 'annonces'],
                'is_active' => true,
            ],
            [
                'name' => 'Etablissement',
                'slug' => 'etablissement',
                'description' => 'Sans limite, support prioritaire.',
                'price' => 75000,
                'billing_cycle' => 'monthly',
                'max_students' => null,
                'max_staff' => null,
                'features' => ['gestion_eleves', 'notes', 'presences', 'finances', 'bulletins', 'annonces', 'api', 'support_prioritaire'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
