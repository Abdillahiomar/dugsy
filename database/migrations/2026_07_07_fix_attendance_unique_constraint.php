<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Récupérer TOUS les index unique existants sur la table attendances
        $indexes = DB::select("
            SELECT DISTINCT index_name
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'attendances'
              AND non_unique   = 0
              AND index_name  != 'PRIMARY'
        ");

        $indexNames = collect($indexes)->pluck('index_name')->toArray();

        // Supprimer tous les index uniques existants (sauf PRIMARY)
        // pour repartir proprement
        Schema::table('attendances', function (Blueprint $table) use ($indexNames) {
            foreach ($indexNames as $name) {
                // Vérifier si c'est un index qu'on veut supprimer
                // (pas notre nouveau index qui inclut session_start)
                if ($name !== 'attendance_student_date_session_unique') {
                    try {
                        $table->dropUnique($name);
                    } catch (\Exception $e) {
                        // Ignorer si déjà supprimé
                    }
                }
            }
        });

        // Vérifier que notre nouveau index existe bien
        $newIndexExists = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'attendances'
              AND index_name   = 'attendance_student_date_session_unique'
        ");

        if ($newIndexExists[0]->cnt === 0) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->unique(
                    ['student_school_year_id', 'date', 'session_start'],
                    'attendance_student_date_session_unique'
                );
            });
        }
    }

    public function down(): void
    {
        // Restaurer l'ancienne contrainte simple
        Schema::table('attendances', function (Blueprint $table) {
            try {
                $table->dropUnique('attendance_student_date_session_unique');
            } catch (\Exception) {}

            $table->unique(['student_school_year_id', 'date'], 'attendance_unique');
        });
    }
};
