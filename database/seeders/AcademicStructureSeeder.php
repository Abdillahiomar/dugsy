<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Level;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $levels = ['CP', 'CE1', 'CE2', 'CM1', 'CM2', '6eme', '5eme', '4eme', '3eme'];

        $subjects = [
            ['name' => 'Mathématiques', 'code' => 'MATH', 'coefficient' => 3],
            ['name' => 'Français', 'code' => 'FR', 'coefficient' => 3],
            ['name' => 'Anglais', 'code' => 'ANG', 'coefficient' => 2],
            ['name' => 'Sciences Physiques', 'code' => 'PHY', 'coefficient' => 2],
            ['name' => 'SVT', 'code' => 'SVT', 'coefficient' => 2],
            ['name' => 'Histoire-Géographie', 'code' => 'HG', 'coefficient' => 2],
            ['name' => 'Education Islamique', 'code' => 'EI', 'coefficient' => 1],
            ['name' => 'Education Physique', 'code' => 'EPS', 'coefficient' => 1],
        ];

        School::all()->each(function (School $school) use ($levels, $subjects) {
            // Année scolaire active
            AcademicYear::updateOrCreate(
                ['school_id' => $school->id, 'label' => '2025-2026'],
                [
                    'starts_at' => '2025-09-01',
                    'ends_at' => '2026-07-15',
                    'is_active' => true,
                ]
            );

            foreach ($levels as $i => $levelName) {
                Level::updateOrCreate(
                    ['school_id' => $school->id, 'name' => $levelName],
                    ['order' => $i + 1]
                );
            }

            foreach ($subjects as $subject) {
                Subject::updateOrCreate(
                    ['school_id' => $school->id, 'name' => $subject['name']],
                    ['code' => $subject['code'], 'coefficient' => $subject['coefficient']]
                );
            }
        });
    }
}
