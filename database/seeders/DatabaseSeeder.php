<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            SchoolSeeder::class,
            AcademicStructureSeeder::class,
            UserStaffSeeder::class,
            SchoolClassSeeder::class,
            ClassSubjectTeacherSeeder::class,
            GuardianSeeder::class,
            StudentSeeder::class,
            FeeStructureSeeder::class,
            StudentInvoiceSeeder::class,
        ]);
    }
}
