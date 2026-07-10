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
        Schema::create('school_grading_configs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();

    // ── Barème ────────────────────────────────────────────────
    $table->unsignedSmallInteger('max_score')->default(20);
    $table->unsignedSmallInteger('passing_score')->default(10);
    $table->unsignedTinyInteger('decimal_places')->default(2);

    // ── Poids des types d'évaluation (en %) ──────────────────
    // La somme doit faire 100
    // Stocké en JSON : {"devoir":40,"controle":30,"examen":30}
    $table->json('evaluation_weights')->nullable();

    // ── Types d'évaluation actifs ─────────────────────────────
    // JSON : ["devoir","controle","interrogation","examen","tp"]
    $table->json('evaluation_types')->nullable();

    // ── Règles de calcul ──────────────────────────────────────
    $table->boolean('drop_lowest_grade')->default(false);
    $table->unsignedTinyInteger('min_grades_per_period')->default(1);

    // ── Mentions personnalisées ───────────────────────────────
    // JSON : [{"label":"Très Bien","min":16},{"label":"Bien","min":14},...]
    $table->json('mentions')->nullable();

    // ── Appréciations automatiques ────────────────────────────
    // JSON : [{"label":"Excellent","min":18,"max":20},...]
    $table->json('appreciations')->nullable();

    // ── Calcul de la moyenne générale ────────────────────────
    // weighted_coefficient : pondéré par coeff matière (standard)
    // simple_average       : moyenne simple sans coeff
    $table->string('average_method')->default('weighted_coefficient');

    // ── Période académique ────────────────────────────────────
    // trimester (3) | semester (2) | annual (1)
    $table->string('period_system')->default('trimester');

    // ── Affichage bulletin ────────────────────────────────────
    $table->boolean('show_rank')->default(true);
    $table->boolean('show_class_average')->default(true);
    $table->boolean('show_min_max')->default(false);
    $table->boolean('show_teacher_appreciation')->default(true);
    $table->boolean('show_absences_on_bulletin')->default(true);

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_grading_configs');
    }
};
