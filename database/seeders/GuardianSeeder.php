<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\School;
use Illuminate\Database\Seeder;

class GuardianSeeder extends Seeder
{
    private array $firstNames = ['Ahmed', 'Fatouma', 'Houssein', 'Amina', 'Ibrahim', 'Khadija', 'Omar', 'Sahra'];
    private array $lastNames = ['Dirieh', 'Robleh', 'Hassan', 'Ali', 'Farah', 'Guelleh', 'Waiss', 'Doualeh'];

    public function run(): void
    {
        School::all()->each(function (School $school) {
            for ($i = 1; $i <= 20; $i++) {
                Guardian::create([
                    'school_id' => $school->id,
                    'first_name' => $this->firstNames[array_rand($this->firstNames)],
                    'last_name' => $this->lastNames[array_rand($this->lastNames)],
                    'phone' => '77' . random_int(100000, 999999),
                    'email' => null,
                    'profession' => null,
                    'address' => 'Djibouti Ville',
                ]);
            }
        });
    }
}
