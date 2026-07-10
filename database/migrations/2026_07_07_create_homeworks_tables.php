<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->date('due_date');
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('allow_submission')->default(true);
            $table->timestamps();
        });

        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();

            // ← constrained('homeworks') au lieu de constrained()
            $table->foreignId('homework_id')
                ->constrained('homeworks')   // ← FIX
                ->cascadeOnDelete();

            $table->foreignId('student_school_year_id')
                ->constrained('student_school_years')
                ->cascadeOnDelete();

            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_size')->nullable();

            $table->foreignId('submitted_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('submitted_at');
            $table->string('status')->default('submitted');
            $table->decimal('grade', 5, 2)->nullable();
            $table->text('teacher_comment')->nullable();
            $table->timestamp('graded_at')->nullable();

            $table->unique(
                ['homework_id', 'student_school_year_id'],
                'hw_submission_unique'
            );

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homeworks');
    }
};