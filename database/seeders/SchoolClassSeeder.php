<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\School;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    public function run(): void
    {
        School::all()->each(function (School $school) {
            $year = AcademicYear::where('school_id', $school->id)->where('is_active', true)->first();
            $teachers = Staff::where('school_id', $school->id)->where('position', 'Enseignant')->get();

            // On ne crée des classes que pour les 3 premiers niveaux pour garder un seed léger
            $levels = $school->levels()->orderBy('order')->take(3)->get();

            foreach ($levels as $level) {
                foreach (['A', 'B'] as $section) {
                    SchoolClass::updateOrCreate(
                        [
                            'academic_year_id' => $year->id,
                            'level_id' => $level->id,
                            'name' => $level->name . ' ' . $section,
                        ],
                        [
                            'school_id' => $school->id,
                            'main_teacher_id' => $teachers->isNotEmpty() ? $teachers->random()->id : null,
                            'capacity' => 35,
                        ]
                    );
                }
            }
        });
    }
}
