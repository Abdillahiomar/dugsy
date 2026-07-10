<?php

namespace Database\Seeders;

use App\Models\ClassSubjectTeacher;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class ClassSubjectTeacherSeeder extends Seeder
{
    public function run(): void
    {
        School::all()->each(function (School $school) {
            $teachers = Staff::where('school_id', $school->id)->where('position', 'Enseignant')->get();
            $subjects = $school->subjects;

            if ($teachers->isEmpty()) {
                return;
            }

            SchoolClass::where('school_id', $school->id)->get()->each(function (SchoolClass $class) use ($subjects, $teachers) {
                foreach ($subjects as $subject) {
                    ClassSubjectTeacher::updateOrCreate(
                        [
                            'school_class_id' => $class->id,
                            'subject_id' => $subject->id,
                        ],
                        [
                            'staff_id' => $teachers->random()->id,
                        ]
                    );
                }
            });
        });
    }
}
