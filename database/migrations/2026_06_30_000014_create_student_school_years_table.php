<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_school_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->date('enrolled_at');
            $table->string('status')->default('enrolled'); // enrolled, repeated, transferred, withdrawn
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id'], 'student_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_school_years');
    }
};
