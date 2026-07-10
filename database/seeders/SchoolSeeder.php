<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $schools = [
            ['name' => 'Ecole Les Pyramides', 'slug' => 'les-pyramides', 'plan' => 'pro'],
            ['name' => 'Institut El Amal', 'slug' => 'el-amal', 'plan' => 'basique'],
        ];

        foreach ($schools as $data) {
            $school = School::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'email' => $data['slug'] . '@example.dj',
                    'phone' => '77' . random_int(100000, 999999),
                    'address' => 'Djibouti Ville, Djibouti',
                    'status' => 'active',
                ]
            );

            $plan = Plan::where('slug', $data['plan'])->first();

            Subscription::updateOrCreate(
                ['school_id' => $school->id, 'plan_id' => $plan->id],
                [
                    'status' => 'active',
                    'starts_at' => now()->subMonths(2),
                    'ends_at' => now()->addMonths(10),
                    'auto_renew' => true,
                ]
            );
        }
    }
}
