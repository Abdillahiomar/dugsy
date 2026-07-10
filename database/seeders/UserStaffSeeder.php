<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserStaffSeeder extends Seeder
{
    public function run(): void
    {
        School::all()->each(function (School $school) {
            // Administrateur de l'école
            $admin = User::updateOrCreate(
                ['email' => 'admin@' . $school->slug . '.dj'],
                [
                    'school_id' => $school->id,
                    'name' => 'Admin ' . $school->name,
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            Staff::updateOrCreate(
                ['user_id' => $admin->id],
                [
                    'school_id' => $school->id,
                    'matricule' => 'ADM-' . $school->id . '-001',
                    'position' => 'Administrateur',
                    'hired_at' => now()->subYears(2),
                    'phone' => '77' . random_int(100000, 999999),
                ]
            );

            // 4 enseignants par école
            for ($i = 1; $i <= 4; $i++) {
                $teacherUser = User::updateOrCreate(
                    ['email' => "enseignant{$i}@{$school->slug}.dj"],
                    [
                        'school_id' => $school->id,
                        'name' => "Enseignant {$i} " . $school->name,
                        'password' => Hash::make('password'),
                        'status' => 'active',
                        'email_verified_at' => now(),
                    ]
                );

                Staff::updateOrCreate(
                    ['user_id' => $teacherUser->id],
                    [
                        'school_id' => $school->id,
                        'matricule' => 'ENS-' . $school->id . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                        'position' => 'Enseignant',
                        'hired_at' => now()->subMonths(random_int(3, 24)),
                        'phone' => '77' . random_int(100000, 999999),
                    ]
                );
            }
        });
    }
}
