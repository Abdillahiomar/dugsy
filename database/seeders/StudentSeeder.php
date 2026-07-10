<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    private array $firstNames = ['Ayan', 'Bilal', 'Deeqa', 'Yasin', 'Naima', 'Said', 'Hibo', 'Zakaria', 'Mariam', 'Abdi'];
    private array $lastNames = ['Dirieh', 'Robleh', 'Hassan', 'Ali', 'Farah', 'Guelleh', 'Waiss', 'Doualeh'];

    public function run(): void
    {
        School::all()->each(function (School $school) {
            $year = AcademicYear::where('school_id', $school->id)->where('is_active', true)->first();
            $classes = SchoolClass::where('school_id', $school->id)->get();
            $guardians = Guardian::where('school_id', $school->id)->get();

            if ($classes->isEmpty()) {
                return;
            }

            foreach ($classes as $class) {
                // 15 élèves par classe
                for ($i = 1; $i <= 15; $i++) {
                    $student = Student::create([
                        'school_id' => $school->id,
                        'matricule' => 'ELV-' . $school->id . '-' . $class->id . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                        'first_name' => $this->firstNames[array_rand($this->firstNames)],
                        'last_name' => $this->lastNames[array_rand($this->lastNames)],
                        'birth_date' => now()->subYears(random_int(6, 16))->subDays(random_int(0, 365)),
                        'gender' => random_int(0, 1) ? 'M' : 'F',
                        'status' => 'active',
                    ]);

                    StudentSchoolYear::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $year->id,
                        'school_class_id' => $class->id,
                        'enrolled_at' => $year->starts_at,
                        'status' => 'enrolled',
                    ]);

                    if ($guardians->isNotEmpty()) {
                        $student->guardians()->attach($guardians->random()->id, [
                            'relationship' => 'parent',
                            'is_primary_contact' => true,
                        ]);
                    }
                }
            }
        });
    }
}
