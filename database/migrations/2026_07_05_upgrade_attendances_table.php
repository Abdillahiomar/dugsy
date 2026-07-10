<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancienne contrainte unique SEULEMENT si elle existe
        $this->dropUniqueIfExists('attendances', 'attendances_student_school_year_id_date_unique');

        Schema::table('attendances', function (Blueprint $table) {
            $table->time('session_start')->nullable()->after('date');
            $table->time('session_end')->nullable()->after('session_start');

            $table->foreignId('subject_id')
                ->nullable()
                ->after('session_end')
                ->constrained()
                ->nullOnDelete();

            $table->string('justification_path')->nullable()->after('justification');

            // Nouvelle contrainte : élève + date + heure début
            $table->unique(
                ['student_school_year_id', 'date', 'session_start'],
                'attendance_student_date_session_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendance_student_date_session_unique');
            $table->dropForeign(['subject_id']);
            $table->dropColumn(['session_start', 'session_end', 'subject_id', 'justification_path']);
        });
    }

    /**
     * Supprime un index unique seulement s'il existe en base.
     */
    private function dropUniqueIfExists(string $table, string $indexName): void
    {
        $exists = DB::select(
            "SELECT COUNT(*) as cnt
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = ?
               AND index_name   = ?",
            [$table, $indexName]
        );

        if ($exists[0]->cnt > 0) {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropUnique($indexName);
            });
        }
    }
};