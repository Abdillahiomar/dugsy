<?php
// database/migrations/2026_07_13_create_timetable_slots_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained()->nullOnDelete();

            // 0=Dimanche, 1=Lundi, 2=Mardi, 3=Mercredi, 4=Jeudi, 5=Vendredi, 6=Samedi
            $table->tinyInteger('day_of_week');

            $table->time('start_time');
            $table->time('end_time');
            $table->string('room')->nullable(); // ex: "Salle 3"
            $table->string('color')->nullable(); // couleur héritée de la matière par défaut

            $table->timestamps();

            // Un même créneau ne peut pas être en double pour une classe
            $table->unique(
                ['school_class_id', 'day_of_week', 'start_time'],
                'slot_class_day_time_unique'
            );
        });

        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // exam | holiday | meeting | activity | other
            $table->string('type')->default('other');

            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_all_day')->default(true);
            $table->time('start_time')->nullable(); // si pas all_day
            $table->time('end_time')->nullable();

            $table->string('color')->default('#1E2D5A'); // couleur hex

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_events');
        Schema::dropIfExists('timetable_slots');
    }
};
