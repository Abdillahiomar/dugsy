<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\School;
use Illuminate\Database\Seeder;

class FeeStructureSeeder extends Seeder
{
    public function run(): void
    {
        School::all()->each(function (School $school) {
            $year = AcademicYear::where('school_id', $school->id)->where('is_active', true)->first();
            $levels = $school->levels()->orderBy('order')->take(3)->get();

            foreach ($levels as $level) {
                FeeStructure::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'academic_year_id' => $year->id,
                        'level_id' => $level->id,
                    ],
                    [
                        'label' => 'Frais de scolarité ' . $level->name,
                        'amount' => 120000,
                        'frequency' => 'annual',
                    ]
                );
            }
        });
    }
}
