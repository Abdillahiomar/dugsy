<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('school_admission_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();

            // Critères d'âge
            $table->unsignedTinyInteger('min_age_years')->nullable();
            $table->unsignedTinyInteger('max_age_years')->nullable();

            // Niveaux acceptés (ex: ["CP","CE1","6ème"])
            $table->json('accepted_levels')->nullable();

            // Tests d'admission
            $table->boolean('requires_entrance_test')->default(false);
            $table->text('entrance_test_description')->nullable();

            // Quota par classe
            $table->boolean('enforce_capacity')->default(true);

            // Priorités (ex: fratrie, personnel, zone géo)
            $table->boolean('priority_siblings')->default(false);
            $table->boolean('priority_staff_children')->default(false);

            // Message/conditions affichés lors de l'inscription
            $table->text('admission_conditions')->nullable();

            // Période d'inscription ouverte
            $table->date('enrollment_open_from')->nullable();
            $table->date('enrollment_open_until')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_admission_configs');
    }
};
